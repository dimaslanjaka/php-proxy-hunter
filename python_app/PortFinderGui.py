import sys
from pathlib import Path
import traceback

# Ensure repository root (parent of python_app) is on sys.path so repo imports work.
# Idempotent: only insert if not already present.
_REPO_ROOT = str(Path(__file__).resolve().parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

import threading
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta, timezone

from proxy_hunter import extract_ips, is_port_open
from PySide6.QtCore import Qt, Signal
from PySide6.QtGui import QBrush, QColor, QIcon
from PySide6.QtWidgets import (
    QApplication,
    QCheckBox,
    QHBoxLayout,
    QLabel,
    QLineEdit,
    QPushButton,
    QSpinBox,
    QTableWidget,
    QTableWidgetItem,
    QTabWidget,
    QTextEdit,
    QVBoxLayout,
    QWidget,
)

from src.func import get_nuitka_file, get_relative_path
from src.ProxyDB import ProxyDB
from src.pyside6.utils.settings import load_text, save_text


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
        # Reduce outer margins/spacing so top/bottom padding isn't excessive when maximized
        try:
            layout.setContentsMargins(6, 6, 6, 6)
            layout.setSpacing(6)
        except Exception:
            pass

        self.input_label = QLabel("Enter proxies/text (IPs or ip:port, one per line):")
        layout.addWidget(self.input_label)

        # Create tab widget for proxy input (Manual, Untested, Working)
        self.input_tab_widget = QTabWidget()

        # Tab 1: Manual Input
        manual_tab_widget = QWidget()
        manual_tab_layout = QVBoxLayout()
        self.tab_manual = QTextEdit()
        self.tab_manual.setPlaceholderText(
            "Example:\n123.45.67.89:8080\n10.0.0.2:3128\nor paste raw text to extract IPs"
        )
        # Load saved proxies from previous session
        saved = load_text("portfinder_proxy_text_manual")
        if saved:
            self.tab_manual.setPlainText(saved)
        self.tab_manual.textChanged.connect(self._autosave_manual_text)
        manual_tab_layout.addWidget(self.tab_manual)
        manual_tab_widget.setLayout(manual_tab_layout)
        self.input_tab_widget.addTab(manual_tab_widget, "Manual Input")

        # Tab 2: Untested Proxies
        untested_tab_widget = QWidget()
        untested_tab_layout = QVBoxLayout()
        self.tab_untested = QTextEdit()
        self.tab_untested.setPlaceholderText("Untested proxies will appear here...")
        saved = load_text("portfinder_proxy_text_untested")
        if saved:
            self.tab_untested.setPlainText(saved)
        untested_tab_layout.addWidget(self.tab_untested)

        self.fetch_untested_button = QPushButton("Get Untested Proxies")
        self.fetch_untested_button.clicked.connect(self.fetch_untested_proxies)
        untested_tab_layout.addWidget(self.fetch_untested_button)

        untested_tab_widget.setLayout(untested_tab_layout)
        self.input_tab_widget.addTab(untested_tab_widget, "Untested Proxies")

        # Tab 3: Working Proxies
        working_tab_widget = QWidget()
        working_tab_layout = QVBoxLayout()
        self.tab_working = QTextEdit()
        self.tab_working.setPlaceholderText("Working proxies will appear here...")
        saved = load_text("portfinder_proxy_text_working")
        if saved:
            self.tab_working.setPlainText(saved)
        working_tab_layout.addWidget(self.tab_working)

        self.fetch_working_button = QPushButton("Get Working Proxies")
        self.fetch_working_button.clicked.connect(self.fetch_working_proxies)
        working_tab_layout.addWidget(self.fetch_working_button)

        working_tab_widget.setLayout(working_tab_layout)
        self.input_tab_widget.addTab(working_tab_widget, "Working Proxies")

        layout.addWidget(self.input_tab_widget)

        # Restore saved input tab selection
        try:
            saved_tab = load_text("portfinder_input_tab")
            if saved_tab is not None:
                idx = int(saved_tab)
                if 0 <= idx < self.input_tab_widget.count():
                    self.input_tab_widget.setCurrentIndex(idx)
        except Exception:
            pass

        def _on_input_tab_changed(i):
            try:
                save_text("portfinder_input_tab", str(i))
            except Exception:
                pass

        self.input_tab_widget.currentChanged.connect(_on_input_tab_changed)

        # Port input mode: tabs for Range vs Custom
        self.tab_widget = QTabWidget()

        # Range tab
        range_tab = QWidget()
        range_layout = QHBoxLayout()
        # Keep inner layout compact vertically and add horizontal padding
        try:
            # left, top, right, bottom
            range_layout.setContentsMargins(8, 2, 8, 2)
            range_layout.setSpacing(6)
        except Exception:
            pass
        range_layout.addWidget(QLabel("Port from:"))
        self.port_from = QSpinBox()
        self.port_from.setRange(1, 65535)
        self.port_from.setValue(80)
        range_layout.addWidget(self.port_from)

        range_layout.addWidget(QLabel("to:"))
        self.port_to = QSpinBox()
        self.port_to.setRange(1, 65535)
        self.port_to.setValue(65535)
        range_layout.addWidget(self.port_to)
        range_tab.setLayout(range_layout)

        # Custom tab
        custom_tab = QWidget()
        custom_layout = QHBoxLayout()
        # Keep inner layout compact vertically and add horizontal padding
        try:
            # left, top, right, bottom
            custom_layout.setContentsMargins(8, 2, 8, 2)
            custom_layout.setSpacing(6)
        except Exception:
            pass
        self.custom_ports = QLineEdit()
        self.custom_ports.setPlaceholderText("Custom ports (e.g. 80,443,8000-8100)")
        # Restore saved custom ports
        try:
            saved_custom = load_text("portfinder_custom_ports")
            if saved_custom:
                self.custom_ports.setText(saved_custom)
        except Exception:
            pass
        self.custom_ports.textChanged.connect(
            lambda v: save_text("portfinder_custom_ports", v)
        )
        custom_layout.addWidget(self.custom_ports)
        custom_tab.setLayout(custom_layout)

        self.tab_widget.addTab(range_tab, "Range")
        self.tab_widget.addTab(custom_tab, "Custom")
        # Limit tab widget height so its contents remain compact when window is maximized
        try:
            self.tab_widget.setMaximumHeight(80)
        except Exception:
            pass

        # Restore saved port values for Range tab
        try:
            saved_from = load_text("portfinder_from")
            saved_to = load_text("portfinder_to")
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

        # Restore saved tab selection
        try:
            saved_tab = load_text("portfinder_port_mode")
            if saved_tab is not None:
                idx = int(saved_tab)
                if 0 <= idx < self.tab_widget.count():
                    self.tab_widget.setCurrentIndex(idx)
        except Exception:
            pass

        def _on_tab_changed(i):
            try:
                save_text("portfinder_port_mode", str(i))
            except Exception:
                pass

        self.tab_widget.currentChanged.connect(_on_tab_changed)
        layout.addWidget(self.tab_widget)

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

        # Initialize shared ProxyDB for other components to use.
        # Keep a lock because scanning runs worker threads which may
        # update the DB concurrently elsewhere in the app.
        try:
            self.db = ProxyDB(get_relative_path(".cache/database.sqlite"), True)
            self.db_lock = threading.Lock()
        except Exception:
            self.db = None
            self.db_lock = threading.Lock()

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

    def _autosave_manual_text(self):
        try:
            save_text("portfinder_proxy_text_manual", self.tab_manual.toPlainText())
        except Exception:
            pass

    # --------------------
    # Fetch proxies from database
    # --------------------
    def fetch_untested_proxies(self):
        """Fetch untested proxies from database and populate the Untested tab"""
        try:
            self.fetch_untested_button.setEnabled(False)
            proxies = self.db.get_untested_proxies(limit=100) if self.db else []
            proxy_list = "\n".join(
                [str(p.get("proxy", "")) for p in proxies if p.get("proxy")]
            )
            self.tab_untested.setPlainText(proxy_list)
            save_text("portfinder_proxy_text_untested", proxy_list)
        except Exception as e:
            print(f"Error fetching untested proxies: {e}")
            traceback.print_exc()
        finally:
            self.fetch_untested_button.setEnabled(True)

    def fetch_working_proxies(self):
        """Fetch working proxies from database and populate the Working tab"""
        try:
            self.fetch_working_button.setEnabled(False)
            proxies = self.db.get_working_proxies(limit=100) if self.db else []
            proxy_list = "\n".join(
                [str(p.get("proxy", "")) for p in proxies if p.get("proxy")]
            )
            self.tab_working.setPlainText(proxy_list)
            save_text("portfinder_proxy_text_working", proxy_list)
        except Exception as e:
            print(f"Error fetching working proxies: {e}")
            traceback.print_exc()
        finally:
            self.fetch_working_button.setEnabled(True)

    def run_scan(self):
        # Get the active input tab
        active_tab_index = self.input_tab_widget.currentIndex()

        # Get the correct textarea based on active tab
        if active_tab_index == 0:  # Manual Input
            active_tab = self.tab_manual
        elif active_tab_index == 1:  # Untested Proxies
            active_tab = self.tab_untested
        elif active_tab_index == 2:  # Working Proxies
            active_tab = self.tab_working
        else:
            return

        # Save current textarea to persistent settings
        if active_tab_index == 0:
            save_text("portfinder_proxy_text_manual", active_tab.toPlainText())
        elif active_tab_index == 1:
            save_text("portfinder_proxy_text_untested", active_tab.toPlainText())
        elif active_tab_index == 2:
            save_text("portfinder_proxy_text_working", active_tab.toPlainText())

        # Save port settings
        save_text("portfinder_from", str(self.port_from.value()))
        save_text("portfinder_to", str(self.port_to.value()))

        text = active_tab.toPlainText()

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

        # Determine ports to scan. If custom ports input is provided, parse it
        # (supports comma-separated values and ranges like 8000-8100). Otherwise
        # use the spinbox range.
        custom_spec = (
            self.custom_ports.text().strip() if hasattr(self, "custom_ports") else ""
        )
        ports_list = []
        if custom_spec:
            try:
                parts = [p.strip() for p in custom_spec.split(",") if p.strip()]
                seen = set()
                for part in parts:
                    if "-" in part:
                        a, b = (x.strip() for x in part.split("-", 1))
                        try:
                            a_i = int(a)
                            b_i = int(b)
                        except Exception:
                            continue
                        if a_i > b_i:
                            a_i, b_i = b_i, a_i
                        for port in range(max(1, a_i), min(65535, b_i) + 1):
                            if port not in seen:
                                seen.add(port)
                                ports_list.append(port)
                    else:
                        try:
                            p_i = int(part)
                        except Exception:
                            continue
                        if 1 <= p_i <= 65535 and p_i not in seen:
                            seen.add(p_i)
                            ports_list.append(p_i)
            except Exception:
                ports_list = []
        if not ports_list:
            ports_list = list(range(port_from, port_to + 1))

        self.status_signal.emit(
            f"Preparing scan: {len(ips)} IP(s), ports {port_from}-{port_to} -> total {self._total_tasks} tasks"
        )

        # Run background scanning
        threading.Thread(
            target=self._scan_background,
            args=(ips, ports_list, port_from, port_to),
            daemon=True,
        ).start()
        # enable abort button
        try:
            self.abort_button.setEnabled(True)
        except Exception:
            pass

    def _scan_background(self, ips, ports_list, port_from, port_to):
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
                for port in ports_list:
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

        # Track open count and store in DB if open
        if data.get("open"):
            self._open_count += 1
            print(f"Open port found: {data.get('ip')}:{data.get('port')}")
            # Store open port in the DB with thread safety
            try:
                if self.db is not None and self.db_lock is not None:
                    port_entry = f"{data.get('ip')}:{data.get('port')}"
                    with self.db_lock:
                        # Add or update the port entry as an active proxy
                        try:
                            existing = self.db.select(port_entry)
                            # check if list is empty
                            if not existing:
                                self.db.update_status(port_entry, "port-open")
                            else:
                                existing_status = existing[0].get("status", "")
                                if (
                                    existing_status != "active"
                                    and existing_status != "port-open"
                                ):
                                    self.db.update_status(port_entry, "port-open")
                        except Exception as e:
                            print(f"Error storing open port {port_entry} in DB: {e}")
            except Exception as e:
                print(f"Error accessing DB for open port: {e}")

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

    def closeEvent(self, event):
        # Ensure DB is closed on application exit to flush resources.
        try:
            if self.db is not None:
                with self.db_lock:
                    try:
                        self.db.close()
                    except Exception:
                        pass
        except Exception:
            pass
        try:
            super().closeEvent(event)
        except Exception:
            # Some embed/run contexts may not expect a super call; ignore errors.
            pass


if __name__ == "__main__":
    app = QApplication(sys.argv)
    window = PortFinder()
    window.show()
    sys.exit(app.exec())
