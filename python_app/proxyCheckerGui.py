import sys
from pathlib import Path

# Ensure repository root (parent of python_app) is on sys.path so repo imports work.
# Idempotent: only insert if not already present.
_REPO_ROOT = str(Path(__file__).resolve().parent.parent)
if _REPO_ROOT not in sys.path:
    sys.path.insert(0, _REPO_ROOT)

from src.ProxyDB import ProxyDB
from src.func import get_nuitka_file, get_relative_path
from concurrent.futures import ThreadPoolExecutor, as_completed
import threading
from PySide6.QtWidgets import (
    QApplication,
    QWidget,
    QVBoxLayout,
    QPushButton,
    QTextEdit,
    QTableWidget,
    QTableWidgetItem,
    QLabel,
)
from PySide6.QtCore import Qt, Signal
from src.pyside6.utils.settings import save_text, load_text
from PySide6.QtGui import QIcon, QColor, QBrush
from proxy_hunter import build_request, extract_proxies, Proxy
from src.geoPlugin import get_geo_ip2
import traceback


class ProxyChecker(QWidget):
    # Emitted from background threads to update the UI safely. Now emits a single Proxy object.
    result_signal = Signal(object)
    finished_signal = Signal()

    def __init__(self):
        super().__init__()

        self.setWindowTitle("Proxy Checker Result - DL Traffic")
        self.setWindowIcon(QIcon(get_nuitka_file("favicon.ico")))
        self.resize(800, 600)
        self.setMinimumSize(500, 400)

        self.db = ProxyDB(get_relative_path(".cache/database.sqlite"), True)

        layout = QVBoxLayout()

        self.input_label = QLabel("Enter proxies (one per line):")
        layout.addWidget(self.input_label)

        self.proxy_text = QTextEdit()
        self.proxy_text.setPlaceholderText("Example:\n123.45.67.89:8080\n10.0.0.2:3128")
        layout.addWidget(self.proxy_text)

        # Load saved proxies from previous session (if any)
        saved = load_text("proxy_text")
        if saved:
            self.proxy_text.setPlainText(saved)

        self.check_button = QPushButton("Check Proxies")
        self.check_button.clicked.connect(self.run_checker)
        layout.addWidget(self.check_button)

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

    # --------------------
    # Proxy checker logic
    # --------------------
    def check_proxy(self, data: Proxy):
        # Notify server
        try:
            build_request(
                method="POST",
                endpoint="https://sh.webmanajemen.com/php_backend/proxy-add.php",
                post_data={"proxy": data.format()},
                timeout=10,
            )
            build_request(
                method="POST",
                endpoint="https://sh.webmanajemen.com/php_backend/proxy-checker.php",
                post_data={"proxy": data.format()},
                timeout=10,
            )
        except:
            pass
        db = ProxyDB(get_relative_path(".cache/database.sqlite"), True)
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
        # Save current textarea to persistent settings so it can be restored later
        save_text("proxy_text", self.proxy_text.toPlainText())

        text = self.proxy_text.toPlainText()
        # Try to extract proxies using proxy_hunter.extract_proxies.
        # If extraction fails or returns an unexpected type, fall back to simple splitlines parsing.
        proxies = extract_proxies(text)

        # Clear previous results
        self.result_table.setRowCount(0)

        if not proxies:
            return

        # Disable button to prevent re-entrancy while checking
        self.check_button.setEnabled(False)

        # Run the blocking check loop in a separate thread so the Qt event loop stays responsive.
        threading.Thread(
            target=self._run_checks_background, args=(proxies,), daemon=True
        ).start()

    def _run_checks_background(self, proxies):
        try:
            with ThreadPoolExecutor(max_workers=20) as executor:
                futures = {executor.submit(self.check_proxy, p): p for p in proxies}

                for f in as_completed(futures):
                    try:
                        proxy = f.result()
                    except Exception as e:
                        # If a worker raises, skip. Keep UI responsive.
                        print(f"Worker error: {e}")
                        continue
                    # Emit signal to update UI from the main thread with single Proxy object
                    self.result_signal.emit(proxy)
        finally:
            # Re-enable the button in the main thread when done
            self.finished_signal.emit()

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


# --------------------
# App entry
# --------------------
if __name__ == "__main__":
    app = QApplication(sys.argv)
    window = ProxyChecker()
    window.show()
    sys.exit(app.exec())
