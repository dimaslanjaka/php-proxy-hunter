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
)
from PySide6.QtCore import Qt, Signal
from PySide6.QtGui import QBrush, QColor, QIcon

from src.pyside6.utils.settings import save_text, load_text
from src.func import get_nuitka_file
from proxy_hunter import extract_proxies, is_port_open
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed


class PortFinder(QWidget):
    result_signal = Signal(object)
    finished_signal = Signal()

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
        layout.addWidget(self.check_button)

        # Result table
        self.result_table = QTableWidget(0, 3)
        self.result_table.setHorizontalHeaderLabels(["IP", "Port", "Open"])
        self.result_table.horizontalHeader().setStretchLastSection(True)
        layout.addWidget(self.result_table)

        self.setLayout(layout)

        # Connect signals
        self.result_signal.connect(self._add_result)
        self.finished_signal.connect(self._on_finished)

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
        proxies = extract_proxies(text)

        # Derive unique IPs from proxies
        ips = []
        for p in proxies:
            try:
                ip = p.proxy.split(":")[0]
            except Exception:
                continue
            if ip not in ips:
                ips.append(ip)

        # If no proxies found, try to extract bare IPs using extractor helper (fallback)
        if not ips:
            # attempt to find IPs by simple regex from extractor module
            try:
                from proxy_hunter.extractor import extract_ips

                ips = extract_ips(text)
            except Exception:
                ips = []

        # Clear table
        self.result_table.setRowCount(0)

        if not ips:
            return

        # Disable button while scanning
        self.check_button.setEnabled(False)

        port_from = int(self.port_from.value())
        port_to = int(self.port_to.value())

        # Run background scanning
        threading.Thread(
            target=self._scan_background, args=(ips, port_from, port_to), daemon=True
        ).start()

    def _scan_background(self, ips, port_from, port_to):
        try:
            tasks = []
            with ThreadPoolExecutor(max_workers=50) as ex:
                futures = {}
                for ip in ips:
                    for port in range(port_from, port_to + 1):
                        addr = f"{ip}:{port}"
                        fut = ex.submit(is_port_open, addr)
                        futures[fut] = (ip, port)

                for f in as_completed(futures):
                    try:
                        ok = f.result()
                    except Exception:
                        ok = False
                    ip, port = futures[f]
                    self.result_signal.emit({"ip": ip, "port": port, "open": bool(ok)})
        finally:
            self.finished_signal.emit()

    def _add_result(self, data):
        row = self.result_table.rowCount()
        self.result_table.insertRow(row)
        self.result_table.setItem(row, 0, QTableWidgetItem(str(data.get("ip", "-"))))
        self.result_table.setItem(row, 1, QTableWidgetItem(str(data.get("port", "-"))))
        item = QTableWidgetItem("yes" if data.get("open") else "no")
        if data.get("open"):
            item.setForeground(QBrush(QColor("green")))
        else:
            item.setForeground(QBrush(QColor("red")))
        self.result_table.setItem(row, 2, item)

    def _on_finished(self):
        self.check_button.setEnabled(True)


if __name__ == "__main__":
    app = QApplication(sys.argv)
    window = PortFinder()
    window.show()
    sys.exit(app.exec())
