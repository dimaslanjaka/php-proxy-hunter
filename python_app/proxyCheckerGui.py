import sys
from pathlib import Path
import threading
import time
import traceback
from datetime import timedelta
import os

# Ensure repository root (parent of python_app) is on sys.path so repo imports work.
# Idempotent: only insert if not already present.
_REPO_ROOT = str(Path(__file__).resolve().parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

from src.shared import init_readonly_db
from src.func import get_nuitka_file, get_relative_path
from PySide6.QtWidgets import (
    QApplication,
    QWidget,
    QVBoxLayout,
    QHBoxLayout,
    QPushButton,
    QTextEdit,
    QTableWidget,
    QTableWidgetItem,
    QLabel,
    QTabWidget,
)
from PySide6.QtCore import Qt, Signal, QThread
from PySide6.QtGui import QIcon, QColor, QBrush
from src.pyside6.utils.settings import save_text, load_text
from proxy_hunter import build_request, extract_proxies, Proxy
from src.geoPlugin import get_geo_ip2


class CheckerWorkerThread(QThread):
    """Background worker thread for proxy checking"""

    result_signal = Signal(object)
    finished_signal = Signal()
    status_signal = Signal(str)

    def __init__(self, proxies, checker_instance):
        super().__init__()
        self.proxies = proxies
        self.checker = checker_instance

    def run(self):
        """Run the check loop in background thread - sequentially, 1 by 1"""
        # Lower thread priority to prevent audio interruptions
        try:
            if sys.platform != "win32" and hasattr(os, "nice"):
                os.nice(10)  # Linux/Mac: lower priority
        except:
            pass

        try:
            self.checker._active_count = 1  # Only 1 proxy being checked at a time

            for proxy in self.proxies:
                # Check if cancel was requested
                if self.checker._cancel_event and self.checker._cancel_event.is_set():
                    break

                try:
                    self.checker.status_signal.emit(f"Checking proxy: {proxy.proxy}")
                    result = self.checker.check_proxy(proxy)
                    self.checker._completed += 1
                    # Emit signal to update UI
                    self.result_signal.emit(result)
                    self.checker._update_progress()
                except Exception as e:
                    print(f"Error checking proxy: {e}")
                    self.checker._completed += 1
                    continue

                # Small sleep to let other threads run (audio, UI, etc.)
                time.sleep(0.05)
        finally:
            self.finished_signal.emit()


class ProxyChecker(QWidget):
    # Emitted from background threads to update the UI safely. Now emits a single Proxy object.
    result_signal = Signal(object)
    finished_signal = Signal()
    status_signal = Signal(str)

    def __init__(self):
        super().__init__()

        self.setWindowTitle("Proxy Checker Result - DL Traffic")
        self.setWindowIcon(QIcon(get_nuitka_file("favicon.ico")))
        self.resize(800, 600)
        self.setMinimumSize(500, 400)

        self.db = init_readonly_db()

        layout = QVBoxLayout()

        self.input_label = QLabel("Enter proxies (one per line):")
        layout.addWidget(self.input_label)

        # Create tab widget with 3 tabs
        self.tab_widget = QTabWidget()

        # Tab 1: Manual Input
        manual_tab_widget = QWidget()
        manual_tab_layout = QVBoxLayout()
        self.tab_manual = QTextEdit()
        self.tab_manual.setPlaceholderText("Example:\n123.45.67.89:8080\n10.0.0.2:3128")
        # Load saved proxies from previous session
        saved = load_text("proxy_text_manual")
        if saved:
            self.tab_manual.setPlainText(saved)
        manual_tab_layout.addWidget(self.tab_manual)
        manual_tab_widget.setLayout(manual_tab_layout)
        self.tab_widget.addTab(manual_tab_widget, "Manual Input")

        # Tab 2: Untested Proxies
        untested_tab_widget = QWidget()
        untested_tab_layout = QVBoxLayout()
        self.tab_untested = QTextEdit()
        self.tab_untested.setPlaceholderText("Untested proxies will appear here...")
        saved = load_text("proxy_text_untested")
        if saved:
            self.tab_untested.setPlainText(saved)
        untested_tab_layout.addWidget(self.tab_untested)

        self.fetch_untested_button = QPushButton("Get Untested Proxies")
        self.fetch_untested_button.clicked.connect(self.fetch_untested_proxies)
        untested_tab_layout.addWidget(self.fetch_untested_button)

        untested_tab_widget.setLayout(untested_tab_layout)
        self.tab_widget.addTab(untested_tab_widget, "Untested Proxies")

        # Tab 3: Working Proxies
        working_tab_widget = QWidget()
        working_tab_layout = QVBoxLayout()
        self.tab_working = QTextEdit()
        self.tab_working.setPlaceholderText("Working proxies will appear here...")
        saved = load_text("proxy_text_working")
        if saved:
            self.tab_working.setPlainText(saved)
        working_tab_layout.addWidget(self.tab_working)

        self.fetch_working_button = QPushButton("Get Working Proxies")
        self.fetch_working_button.clicked.connect(self.fetch_working_proxies)
        working_tab_layout.addWidget(self.fetch_working_button)

        working_tab_widget.setLayout(working_tab_layout)
        self.tab_widget.addTab(working_tab_widget, "Working Proxies")

        layout.addWidget(self.tab_widget)

        # Buttons layout for Check and Stop
        buttons_layout = QHBoxLayout()

        self.check_button = QPushButton("Check Proxies")
        self.check_button.clicked.connect(self.run_checker)
        buttons_layout.addWidget(self.check_button)

        self.stop_button = QPushButton("Stop")
        self.stop_button.clicked.connect(self.stop_checker)
        self.stop_button.setEnabled(False)
        buttons_layout.addWidget(self.stop_button)

        layout.addLayout(buttons_layout)

        # Progress label
        self.progress_label = QLabel("")
        layout.addWidget(self.progress_label)

        # Show detailed proxy properties in the table
        self.result_table = QTableWidget(0, 8)
        self.result_table.setHorizontalHeaderLabels(
            [
                "Proxy",
                "Type",
                "Country",
                "City",
                "Anonymity",
                "Latency",
                "Credentials",
                "Status",
            ]
        )
        self.result_table.horizontalHeader().setStretchLastSection(True)
        layout.addWidget(self.result_table)

        self.setLayout(layout)

        # Connect background signals to UI slots
        self.result_signal.connect(self.add_result)
        self.finished_signal.connect(self._on_finished)
        self.status_signal.connect(self._set_status_text)

        # Progress tracking
        self._total_proxies = 0
        self._completed = 0
        self._active_count = 0
        self._start_time = None
        self._cancel_event = None
        self._executor = None

    # --------------------
    # Status update
    # --------------------
    def stop_checker(self):
        """Stop the checking process"""
        self._cancel_event.set() if self._cancel_event else None
        self.status_signal.emit("Stop requested — cancelling pending checks...")
        self.stop_button.setEnabled(False)

    def _set_status_text(self, text: str):
        """Update progress label from background thread"""
        try:
            self.progress_label.setText(text)
        except Exception:
            pass

    def _update_progress(self):
        """Update progress display with current counters"""
        try:
            if self._start_time:
                elapsed = time.time() - self._start_time
                if self._total_proxies > 0 and elapsed > 0:
                    rate = self._completed / elapsed
                    remaining = (
                        (self._total_proxies - self._completed) / rate
                        if rate > 0
                        else 0
                    )

                    eta = str(timedelta(seconds=int(remaining))) if rate > 0 else "?"
                    elapsed_str = str(timedelta(seconds=int(elapsed)))
                    status = f"Checking {self._completed} / {self._total_proxies} (active: {self._active_count}) — elapsed: {elapsed_str} ETA: {eta}"
                else:
                    status = f"Checking {self._completed} / {self._total_proxies} (active: {self._active_count})"
            else:
                status = f"Checking {self._completed} / {self._total_proxies} (active: {self._active_count})"
            self.progress_label.setText(status)
        except Exception:
            pass

    # --------------------
    # Fetch proxies from database
    # --------------------
    def fetch_untested_proxies(self):
        """Fetch untested proxies from database and populate the Untested tab"""
        try:
            self.fetch_untested_button.setEnabled(False)
            proxies = self.db.get_untested_proxies(limit=100)
            proxy_list = "\n".join(
                [str(p.get("proxy", "")) for p in proxies if p.get("proxy")]
            )
            self.tab_untested.setPlainText(proxy_list)
            save_text("proxy_text_untested", proxy_list)
        except Exception as e:
            print(f"Error fetching untested proxies: {e}")
            traceback.print_exc()
        finally:
            self.fetch_untested_button.setEnabled(True)

    def fetch_working_proxies(self):
        """Fetch working proxies from database and populate the Working tab"""
        try:
            self.fetch_working_button.setEnabled(False)
            proxies = self.db.get_working_proxies(limit=100)
            proxy_list = "\n".join(
                [str(p.get("proxy", "")) for p in proxies if p.get("proxy")]
            )
            self.tab_working.setPlainText(proxy_list)
            save_text("proxy_text_working", proxy_list)
        except Exception as e:
            print(f"Error fetching working proxies: {e}")
            traceback.print_exc()
        finally:
            self.fetch_working_button.setEnabled(True)

    # --------------------
    # Proxy checker logic
    # --------------------
    def check_proxy(self, data: Proxy):
        # Notify server with reduced timeouts to prevent long blocking
        try:
            build_request(
                method="POST",
                endpoint="https://sh.webmanajemen.com/php_backend/proxy-add.php",
                post_data={"proxy": data.format()},
                timeout=10,
            )
        except:
            pass
        try:
            build_request(
                method="POST",
                endpoint="https://sh.webmanajemen.com/php_backend/proxy-checker.php",
                post_data={"proxy": data.format()},
                timeout=10,
            )
        except:
            pass

        # Reuse database connection to reduce resource overhead
        db = init_readonly_db()
        try:
            proxy_types = ["http", "socks4", "socks5"]
            working_types = []
            for proxy_type in proxy_types:
                try:
                    response = build_request(
                        proxy=data.proxy,
                        proxy_type=proxy_type,
                        endpoint="http://httpbin.org/ip",
                        timeout=5,
                    )
                    if (
                        response is not None
                        and getattr(response, "status_code", None) == 200
                    ):
                        working_types.append(proxy_type)
                except Exception:
                    continue

            if len(working_types) > 0:
                # update proxy status property and DB
                data.status = "active"
                try:
                    db.update_status(data.proxy, "active")
                except Exception as e:
                    print(f"Error updating status for proxy {data.proxy}: {e}")
                    traceback.print_exc()
                # If country is missing or placeholder, attempt geolocation lookup.
                try:
                    current_country = getattr(data, "country", None)
                    if not current_country or current_country in ("-", ""):
                        proxy_username = getattr(data, "username", None)
                        proxy_password = getattr(data, "password", None)
                        geo = get_geo_ip2(data.proxy, proxy_username, proxy_password)
                        if geo:
                            # Only set fields the UI already expects (`country` and `city`).
                            # Avoid assigning numeric fields or new unknown attributes
                            # to the `Proxy` object to keep static type checkers happy.
                            data.country = geo.country_name or getattr(
                                data, "country", None
                            )
                            data.city = geo.city or getattr(data, "city", None)
                except Exception as e:
                    print(f"Geo lookup failed for {data.proxy}: {e}")
                    traceback.print_exc()

                return data
        except Exception as e:
            print(f"Error checking proxy {data.proxy}: {e}")
            traceback.print_exc()

        data.status = "dead"
        try:
            db.update_status(data.proxy, "dead")
        except Exception as e:
            print(f"Error updating status for proxy {data.proxy}: {e}")
            traceback.print_exc()
        finally:
            try:
                db.close()
            except Exception:
                pass

        return data

    # --------------------
    # Run checker
    # --------------------
    def run_checker(self):
        """Parse proxies and start background checking thread"""
        # Disable button to prevent re-entrancy
        self.check_button.setEnabled(False)
        self.stop_button.setEnabled(True)

        # Get the active tab
        active_tab_index = self.tab_widget.currentIndex()

        # Get the correct textarea based on active tab
        if active_tab_index == 0:  # Manual Input
            active_tab = self.tab_manual
        elif active_tab_index == 1:  # Untested Proxies
            active_tab = self.tab_untested
        elif active_tab_index == 2:  # Working Proxies
            active_tab = self.tab_working
        else:
            self.check_button.setEnabled(True)
            self.stop_button.setEnabled(False)
            return

        # Save current textarea to persistent settings
        if active_tab_index == 0:
            save_text("proxy_text_manual", active_tab.toPlainText())
        elif active_tab_index == 1:
            save_text("proxy_text_untested", active_tab.toPlainText())
        elif active_tab_index == 2:
            save_text("proxy_text_working", active_tab.toPlainText())

        text = active_tab.toPlainText()

        # Extract proxies (this can be slow, so do it in background)
        def parse_proxies():
            self.status_signal.emit("Extracting proxies...")
            try:
                return extract_proxies(text)
            except Exception as e:
                print(f"Error extracting proxies: {e}")
                return []

        # Start parsing in background
        parse_thread = threading.Thread(
            target=self._parse_and_start_check, args=(parse_proxies,), daemon=True
        )
        parse_thread.start()

    def _parse_and_start_check(self, parse_func):
        """Parse proxies and start the checking process"""
        proxies = parse_func()

        # Update UI on main thread
        if not proxies:
            self.check_button.setEnabled(True)
            self.stop_button.setEnabled(False)
            self.status_signal.emit("No proxies found")
            return

        # Clear previous results (on main thread)
        self.result_table.setRowCount(0)

        # Reset progress counters
        self._total_proxies = len(proxies)
        self._completed = 0
        self._active_count = 0
        self._start_time = time.time()
        self._cancel_event = threading.Event()

        # Create and start worker thread
        self.worker_thread = CheckerWorkerThread(proxies, self)
        self.worker_thread.result_signal.connect(self.add_result)
        self.worker_thread.finished_signal.connect(self._on_finished)
        self.worker_thread.status_signal.connect(self._set_status_text)
        self.worker_thread.start()

    # --------------------
    # Update UI with result
    # --------------------
    def add_result(self, data):
        row = self.result_table.rowCount()
        self.result_table.insertRow(row)

        # Populate table with Proxy properties. The `proxy` argument is a Proxy object
        # from packages.proxy-hunter-python. Use safe getattr with fallback to "-".
        def g(attr):
            return (
                str(getattr(data, attr, "-"))
                if getattr(data, attr, None) not in (None, "")
                else "-"
            )

        # Column 0: formatted proxy string (ip:port or proxy@user:pass)
        self.result_table.setItem(row, 0, QTableWidgetItem(data.format()))
        # Column 1: type
        self.result_table.setItem(row, 1, QTableWidgetItem(g("type")))
        # Column 2: country
        self.result_table.setItem(row, 2, QTableWidgetItem(g("country")))
        # Column 3: city
        self.result_table.setItem(row, 3, QTableWidgetItem(g("city")))
        # Column 4: anonymity
        self.result_table.setItem(row, 4, QTableWidgetItem(g("anonymity")))
        # Column 5: latency
        self.result_table.setItem(row, 5, QTableWidgetItem(g("latency")))
        # Column 6: credentials present
        creds = (
            "yes"
            if getattr(data, "has_credentials", None) and data.has_credentials()
            else (
                "no"
                if getattr(data, "username", None) or getattr(data, "password", None)
                else "-"
            )
        )
        self.result_table.setItem(row, 6, QTableWidgetItem(creds))

        # Column 7: status with colored foreground. Use the Proxy.status property if present.
        status = getattr(data, "status", "-")
        item = QTableWidgetItem(status)
        if status == "active":
            item.setForeground(QBrush(QColor("green")))
        else:
            item.setForeground(QBrush(QColor("red")))
        self.result_table.setItem(row, 7, item)

    def _on_finished(self):
        self.check_button.setEnabled(True)
        self.stop_button.setEnabled(False)


# --------------------
# App entry
# --------------------
if __name__ == "__main__":
    app = QApplication(sys.argv)
    window = ProxyChecker()
    window.show()
    sys.exit(app.exec())
