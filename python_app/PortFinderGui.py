import sys
from pathlib import Path

# Ensure repository root (parent of python_app) is on sys.path so repo imports work.
# Idempotent: only insert if not already present.
_REPO_ROOT = str(Path(__file__).resolve().parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

from PySide6.QtWidgets import (
    QApplication,
    QWidget,
    QVBoxLayout,
    QPushButton,
    QTextEdit,
    QTableWidget,
    QTableWidgetItem,
    QLabel,
    QHBoxLayout,
    QSpinBox,
    QCheckBox,
)
from PySide6.QtCore import Qt, Signal
from datetime import datetime, timedelta, timezone
from PySide6.QtGui import QBrush, QColor, QIcon

from src.pyside6.utils.settings import save_text, load_text
from src.func import get_nuitka_file
from proxy_hunter import is_port_open, extract_ips
import threading
from concurrent.futures import ThreadPoolExecutor


class PortFinder(QWidget):
    result_signal = Signal(object)
    finished_signal = Signal()
    status_signal = Signal(str)

    def __init__(self):
        super().__init__()

        self.setWindowTitle("Port Finder - php-proxy-hunter")
        try:
            self.setWindowIcon(QIcon(get_nuitka_file("favicon.ico")))
        except Exception:
            pass
        self.resize(700, 500)
        self.setMinimumSize(500, 400)

        layout = QVBoxLayout()

        self.input_label = QLabel("Enter proxies/text (IPs or ip:port, one per line):")
        layout.addWidget(self.input_label)

        self.proxy_text = QTextEdit()
        self.proxy_text.setPlaceholderText(
            "Example:\n123.45.67.89:8080\n10.0.0.2:3128\nor paste raw text to extract IPs"
        )
        layout.addWidget(self.proxy_text)

        # Restore saved textarea
        saved = load_text("portfinder_proxy_text")
        if saved:
            self.proxy_text.setPlainText(saved)

        # Auto-save on change
        self.proxy_text.textChanged.connect(self._autosave_text)

        # Port range inputs
        hbox = QHBoxLayout()
        hbox.addWidget(QLabel("Port from:"))
        self.port_from = QSpinBox()
        self.port_from.setRange(1, 65535)
        self.port_from.setValue(80)
        hbox.addWidget(self.port_from)

        hbox.addWidget(QLabel("to:"))
        self.port_to = QSpinBox()
        self.port_to.setRange(1, 65535)
        self.port_to.setValue(65535)
        hbox.addWidget(self.port_to)

        # Restore saved port values
        saved_from = load_text("portfinder_from")
        saved_to = load_text("portfinder_to")
        try:
            if saved_from:
                self.port_from.setValue(int(saved_from))
            if saved_to:
                self.port_to.setValue(int(saved_to))
        except Exception:
            pass

        # Auto-save spinboxes
        self.port_from.valueChanged.connect(
            lambda v: save_text("portfinder_from", str(v))
        )
        self.port_to.valueChanged.connect(lambda v: save_text("portfinder_to", str(v)))

        layout.addLayout(hbox)

        self.check_button = QPushButton("Scan Ports")
        self.check_button.clicked.connect(self.run_scan)

        # Abort button to stop scanning
        self.abort_button = QPushButton("Abort")
        self.abort_button.setEnabled(False)
        self.abort_button.clicked.connect(self._on_abort)

        # Place buttons inline
        hbox_buttons = QHBoxLayout()
        hbox_buttons.addWidget(self.check_button)
        hbox_buttons.addWidget(self.abort_button)
        layout.addLayout(hbox_buttons)

        # Option: show only open ports
        self.only_open_cb = QCheckBox("Show only open ports")
        # Restore saved checkbox state (default: checked)
        try:
            saved_only = load_text("portfinder_only_open")
            if saved_only is None:
                checked = True
            else:
                checked = saved_only == "1"
        except Exception:
            checked = True
        self.only_open_cb.setChecked(checked)
        self.only_open_cb.stateChanged.connect(self._only_open_changed)
        layout.addWidget(self.only_open_cb)

        # Progress label
        self.progress_label = QLabel("")
        layout.addWidget(self.progress_label)

        # Result table
        self.result_table = QTableWidget(0, 3)
        self.result_table.setHorizontalHeaderLabels(["IP", "Port", "Open"])
        self.result_table.horizontalHeader().setStretchLastSection(True)
        layout.addWidget(self.result_table)

        self.setLayout(layout)

        # Connect signals
        self.result_signal.connect(self._add_result)
        self.finished_signal.connect(self._on_finished)
        self.status_signal.connect(self._set_status_text)

        # Progress tracking
        self._total_tasks = 0
        self._completed = 0
        self._open_count = 0
        self._start_time = None
        self._cancel_event = None
        self._futures = None
        self._executor = None

    def _autosave_text(self):
        try:
            save_text("portfinder_proxy_text", self.proxy_text.toPlainText())
        except Exception:
            pass

    def run_scan(self):
        # Save current inputs
        save_text("portfinder_proxy_text", self.proxy_text.toPlainText())
        save_text("portfinder_from", str(self.port_from.value()))
        save_text("portfinder_to", str(self.port_to.value()))

        text = self.proxy_text.toPlainText()

        # Use extract_ips to find all unique IP addresses in the input text.
        # This replaces the previous flow that parsed Proxy objects then
        # extracted IPs; `extract_ips` returns a list of unique IP strings.
        try:
            ips = extract_ips(text)
        except Exception:
            ips = []

        # Clear table
        self.result_table.setRowCount(0)

        # Reset progress counters
        self._completed = 0
        self._open_count = 0

        if not ips:
            return

        # Disable button while scanning
        self.check_button.setEnabled(False)

        port_from = int(self.port_from.value())
        port_to = int(self.port_to.value())

        # Setup progress tracking
        self._total_tasks = len(ips) * (port_to - port_from + 1)
        # progress bar removed; use textual status only
        self._completed = 0
        self._open_count = 0
        # start time for ETA
        try:
            self._start_time = datetime.now(timezone.utc)
        except Exception:
            self._start_time = None
        # Inform user about prepared scan
        try:
            self.status_signal.emit(
                f"Preparing scan: {len(ips)} IP(s), ports {port_from}-{port_to} -> total {self._total_tasks} tasks"
            )
        except Exception:
            pass

        # Run background scanning
        threading.Thread(
            target=self._scan_background, args=(ips, port_from, port_to), daemon=True
        ).start()
        # enable abort button
        try:
            self.abort_button.setEnabled(True)
        except Exception:
            pass

    def _scan_background(self, ips, port_from, port_to):
        # Cooperative cancellation: create an event and track futures/executor
        try:
            self._cancel_event = threading.Event()
            created = 0
            self._futures = []
            self._executor = ThreadPoolExecutor(max_workers=50)

            def _task_done_callback(fut, ip, port):
                # If aborted, skip processing results
                try:
                    if self._cancel_event is not None and self._cancel_event.is_set():
                        return
                    if fut.cancelled():
                        return
                    ok = fut.result()
                except Exception:
                    ok = False
                # Emit result to UI via signal
                try:
                    self.result_signal.emit({"ip": ip, "port": port, "open": bool(ok)})
                except Exception:
                    pass

            # Submit tasks and report creation progress periodically
            for ip in ips:
                if self._cancel_event.is_set():
                    break
                for port in range(port_from, port_to + 1):
                    if self._cancel_event.is_set():
                        break
                    addr = f"{ip}:{port}"
                    fut = self._executor.submit(is_port_open, addr)
                    self._futures.append(fut)
                    # attach callback with captured ip/port
                    fut.add_done_callback(
                        lambda fut, ip=ip, port=port: _task_done_callback(fut, ip, port)
                    )
                    created += 1
                    if created % 500 == 0 or created == self._total_tasks:
                        try:
                            self.status_signal.emit(
                                f"Generating tasks: {created} / {self._total_tasks}"
                            )
                        except Exception:
                            pass

            # All tasks submitted (or aborted)
            try:
                if not (self._cancel_event and self._cancel_event.is_set()):
                    self.status_signal.emit("All tasks created, starting scan...")
                else:
                    self.status_signal.emit("Task generation aborted")
            except Exception:
                pass

            # Wait for running tasks to finish unless aborted
            try:
                if self._executor:
                    self._executor.shutdown(wait=True)
            except Exception:
                pass
        finally:
            # Clear executor/futures and reset cancel event
            try:
                self._executor = None
                self._futures = None
                if self._cancel_event is not None and self._cancel_event.is_set():
                    self.status_signal.emit("Scan aborted")
            except Exception:
                pass
            finally:
                self._cancel_event = None
                self.finished_signal.emit()

    def _add_result(self, data):
        row = self.result_table.rowCount()
        # Always update progress counters
        try:
            self._completed += 1
            # no progress bar; textual status is updated below
        except Exception:
            pass

        # Track open count
        if data.get("open"):
            self._open_count += 1

        # Decide whether to show this row based on checkbox
        try:
            show_only_open = bool(self.only_open_cb.isChecked())
        except Exception:
            show_only_open = True

        if (not show_only_open) or (show_only_open and data.get("open")):
            row = self.result_table.rowCount()
            self.result_table.insertRow(row)
            self.result_table.setItem(
                row, 0, QTableWidgetItem(str(data.get("ip", "-")))
            )
            self.result_table.setItem(
                row, 1, QTableWidgetItem(str(data.get("port", "-")))
            )
            item = QTableWidgetItem("yes" if data.get("open") else "no")
            if data.get("open"):
                item.setForeground(QBrush(QColor("green")))
            else:
                item.setForeground(QBrush(QColor("red")))
            self.result_table.setItem(row, 2, item)

        # Update status label, but throttle UI updates to reduce main-thread load
        try:
            if self._completed % 10 == 0 or self._completed == self._total_tasks:
                # compute ETA occasionally
                try:
                    if self._start_time and self._completed > 0:
                        elapsed = datetime.now(timezone.utc) - self._start_time
                        secs = max(elapsed.total_seconds(), 0.0001)
                        rate = self._completed / secs
                        remaining = max(self._total_tasks - self._completed, 0)
                        eta = (
                            str(timedelta(seconds=int(remaining / rate)))
                            if rate > 0
                            else "?"
                        )
                        elapsed_str = str(timedelta(seconds=int(secs)))
                        status = f"Scanning {self._completed} / {self._total_tasks} (open: {self._open_count}) — elapsed: {elapsed_str} ETA: {eta}"
                    else:
                        status = f"Scanning {self._completed} / {self._total_tasks} (open: {self._open_count})"
                except Exception:
                    status = f"Scanning {self._completed} / {self._total_tasks} (open: {self._open_count})"
                self.progress_label.setText(status)
        except Exception:
            pass

    def _only_open_changed(self, state):
        # Save preference
        try:
            save_text("portfinder_only_open", "1" if state else "0")
        except Exception:
            pass
        # Clear table to reflect new filter
        try:
            self.result_table.setRowCount(0)
        except Exception:
            pass

    def _on_abort(self):
        # Signal cancellation and attempt to cancel pending futures
        try:
            if self._cancel_event is None:
                return
            self._cancel_event.set()
            # attempt to cancel futures that haven't started
            if self._futures:
                for fut in list(self._futures):
                    try:
                        fut.cancel()
                    except Exception:
                        pass
            try:
                self.status_signal.emit("Abort requested — cancelling pending tasks...")
            except Exception:
                pass
            # disable abort button to prevent repeat clicks
            try:
                self.abort_button.setEnabled(False)
            except Exception:
                pass
        except Exception:
            pass

    def _on_finished(self):
        self.check_button.setEnabled(True)
        # Finalize progress UI
        try:
            self.progress_label.setText(
                f"Done: {self._completed} / {self._total_tasks} scanned, open: {self._open_count}"
            )
        except Exception:
            pass
        # disable abort button when finished/cleaned up
        try:
            self.abort_button.setEnabled(False)
        except Exception:
            pass

    def _set_status_text(self, text: str):
        try:
            self.progress_label.setText(text)
        except Exception:
            pass


if __name__ == "__main__":
    app = QApplication(sys.argv)
    window = PortFinder()
    window.show()
    sys.exit(app.exec())
