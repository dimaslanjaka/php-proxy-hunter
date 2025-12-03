import sys
import threading
import traceback
from pathlib import Path

# Ensure repository root (parent of python_app) is on sys.path so repo imports work.
_REPO_ROOT = str(Path(__file__).resolve().parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

from src.shared import init_db
from src.func import get_nuitka_file
from src.pyside6.utils.settings import save_text, load_text
from src.utils.date.timeAgo import time_ago as time_ago_util

from PySide6.QtWidgets import (
    QApplication,
    QWidget,
    QVBoxLayout,
    QHBoxLayout,
    QPushButton,
    QLineEdit,
    QTableWidget,
    QTableWidgetItem,
    QHeaderView,
    QTabWidget,
    QLabel,
    QComboBox,
)
from PySide6.QtCore import Signal
from PySide6.QtGui import QIcon, QBrush, QColor


class ProxyList(QWidget):
    status_signal = Signal(str)

    def __init__(self):
        super().__init__()
        self.setWindowTitle("Proxy List - php-proxy-hunter")
        try:
            self.setWindowIcon(QIcon(get_nuitka_file("favicon.ico")))
        except Exception:
            pass

        self.resize(800, 600)

        self.db = None
        try:
            self.db = init_db(db_type="sqlite")
        except Exception:
            self.db = None

        layout = QVBoxLayout()

        # simple top row with refresh only (we show only working proxies)
        top_row = QHBoxLayout()
        self.refresh_button = QPushButton("Refresh Proxies")
        self.refresh_button.clicked.connect(self.refresh_working)
        top_row.addWidget(self.refresh_button)
        # Status filter dropdown
        self.status_combo = QComboBox()
        # Display labels but store lowercase values as user-friendly keys
        self._status_items = [
            ("All", "all"),
            ("Active", "active"),
            ("Dead", "dead"),
            ("Untested", "untested"),
            ("Port-Closed", "port-closed"),
            ("Port-Open", "port-open"),
        ]
        for label, _val in self._status_items:
            self.status_combo.addItem(label)
        self.status_combo.setCurrentIndex(0)
        self.status_combo.currentIndexChanged.connect(self._on_status_changed)
        top_row.addWidget(QLabel("Status:"))
        top_row.addWidget(self.status_combo)
        layout.addLayout(top_row)

        # Table showing working proxies (add Timezone and Last Check columns)
        self.table = QTableWidget(0, 7)
        self.table.setHorizontalHeaderLabels(
            ["Proxy", "Type", "Country", "City", "Timezone", "Last Check", "Status"]
        )
        header = self.table.horizontalHeader()
        # Make all columns stretch to fill the available table width so
        # the table remains full-width even when cells have little content.
        for i in range(self.table.columnCount()):
            # Use ResizeMode enum to keep type checkers happy
            header.setSectionResizeMode(i, QHeaderView.ResizeMode.Stretch)
        layout.addWidget(self.table)

        self.status_label = QLabel("")
        layout.addWidget(self.status_label)

        self.setLayout(layout)

        self._proxies_cache = []
        self._lock = threading.Lock()
        self._current_status_filter = "all"

        # initial load
        self.refresh_working()

    def _set_status(self, text: str):
        try:
            self.status_label.setText(text)
        except Exception:
            pass

    def _on_status_changed(self, index: int):
        try:
            if 0 <= index < len(self._status_items):
                self._current_status_filter = self._status_items[index][1]
            else:
                self._current_status_filter = "all"
        except Exception:
            self._current_status_filter = "all"

        # Re-populate table from cache with new status filter
        try:
            with self._lock:
                records = list(self._proxies_cache)
            self._populate_table(records, filter_text="")
        except Exception:
            pass

    def refresh_working(self):
        threading.Thread(target=self._fetch_working, daemon=True).start()

    def _fetch_working(self):
        try:
            # Fetch according to currently selected status filter
            status_filter = getattr(self, "_current_status_filter", "all") or "all"
            status_label = status_filter.replace("-", " ").title()
            self._set_status(f"Fetching {status_label} proxies...")
            if self.db is None:
                try:
                    self.db = init_db(db_type="sqlite")
                except Exception:
                    self.db = None

            if self.db is None:
                self._set_status("No DB available")
                return

            try:
                # Choose DB method based on requested status when available
                if status_filter == "all" and hasattr(self.db, "get_all_proxies"):
                    proxies = self.db.get_all_proxies(limit=1000)
                elif status_filter in ("active", "working") and hasattr(
                    self.db, "get_working_proxies"
                ):
                    proxies = self.db.get_working_proxies(limit=1000, auto_fix=False)
                elif status_filter == "dead" and hasattr(self.db, "get_dead_proxies"):
                    proxies = self.db.get_dead_proxies(limit=1000)
                elif status_filter == "untested" and hasattr(
                    self.db, "get_untested_proxies"
                ):
                    proxies = self.db.get_untested_proxies(limit=1000)
                else:
                    # Fallback: fetch all and filter in Python for specific status values
                    if hasattr(self.db, "get_all_proxies"):
                        allp = self.db.get_all_proxies(limit=1000)
                    else:
                        allp = []
                    if status_filter in ("port-closed", "port-open"):
                        proxies = [
                            p
                            for p in allp
                            if str(p.get("status", "")).lower() == status_filter
                        ]
                    else:
                        proxies = allp

                print(
                    f"Fetched {len(proxies)} proxies for status '{status_filter}' from DB"
                )
            except Exception as e:
                print(f"Error fetching proxies for status '{status_filter}': {e}")
                traceback.print_exc()
                proxies = []

            # Normalize records
            records = []
            for p in proxies:
                try:
                    if isinstance(p, dict):
                        rec = p
                    else:
                        rec = {
                            "proxy": getattr(p, "proxy", str(p)),
                            "type": getattr(p, "type", "-"),
                            "country": getattr(p, "country", "-"),
                            "city": getattr(p, "city", "-"),
                            "timezone": getattr(p, "timezone", "-"),
                            "last_check": getattr(p, "last_check", "-"),
                            "status": getattr(p, "status", "-"),
                        }
                    if "proxy" not in rec:
                        rec["proxy"] = str(p)
                    records.append(rec)
                except Exception:
                    continue

            with self._lock:
                self._proxies_cache = records

            self._populate_table(records, filter_text="")
            self._set_status(f"Loaded {len(records)} proxies")
        except Exception:
            traceback.print_exc()
            self._set_status("Error fetching proxies")

    def _populate_table(self, records, filter_text: str = ""):
        try:
            filter_text = filter_text.lower().strip()
            self.table.setRowCount(0)
            # Determine active status filter value
            status_filter = getattr(self, "_current_status_filter", "all")
            if status_filter is None:
                status_filter = "all"
            for rec in records:
                p = str(rec.get("proxy", "-"))
                t_raw = str(rec.get("type", "-"))
                # Normalize hyphen-separated type strings like
                # "http-socks4-socks5" -> "HTTP SOCKS4 SOCKS5"
                if "-" in t_raw:
                    parts = [
                        part.strip().upper()
                        for part in t_raw.split("-")
                        if part.strip()
                    ]
                    t = " ".join(parts) if parts else t_raw.upper()
                else:
                    t = t_raw.upper()
                country = str(rec.get("country", "-"))
                city = str(rec.get("city", "-"))
                timezone = str(rec.get("timezone", "-"))
                last_check_raw = rec.get("last_check", "-")
                last_check = str(last_check_raw)
                try:
                    # Format last_check using shared utility; pass raw value (str or datetime)
                    last_check_display = (
                        time_ago_util(last_check_raw)
                        if last_check_raw and last_check_raw != "-"
                        else "-"
                    )
                except Exception:
                    last_check_display = last_check
                status = str(rec.get("status", "-"))

                # Apply status filter: if not 'all', only show matching statuses
                status_l = status.lower() if status else ""
                if status_filter and status_filter != "all":
                    # Treat 'working' as 'active' synonym
                    if status_filter == "active":
                        if status_l not in ("active", "working"):
                            continue
                    else:
                        if status_l != status_filter:
                            continue

                combined = " ".join(
                    [p, t, country, city, timezone, last_check_display, status]
                ).lower()
                if filter_text and filter_text not in combined:
                    continue

                row = self.table.rowCount()
                self.table.insertRow(row)
                self.table.setItem(row, 0, QTableWidgetItem(p))
                self.table.setItem(row, 1, QTableWidgetItem(t))
                self.table.setItem(row, 2, QTableWidgetItem(country))
                self.table.setItem(row, 3, QTableWidgetItem(city))
                self.table.setItem(row, 4, QTableWidgetItem(timezone))
                self.table.setItem(row, 5, QTableWidgetItem(last_check_display))
                item = QTableWidgetItem(status)
                if status == "active" or status == "working":
                    item.setForeground(QBrush(QColor("green")))
                elif status == "dead" or status == "dead":
                    item.setForeground(QBrush(QColor("red")))
                self.table.setItem(row, 6, item)
            # Auto-size columns after filling rows so widths match content
            # Keep columns stretched to the widget width instead of
            # resizing to content so the table always uses full width.
            # (Avoid calling `resizeColumnsToContents()` which shrinks
            # columns when cell content is small.)
            pass
        except Exception:
            traceback.print_exc()


if __name__ == "__main__":
    app = QApplication(sys.argv)
    w = ProxyList()
    w.show()
    sys.exit(app.exec())
