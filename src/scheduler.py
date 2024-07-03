import atexit
import threading
from typing import Callable, List

class SchedulerHelper:
    """
    A helper class to manage scheduling functions to run at program exit.
    """

    def __init__(self) -> None:
        """
        Initialize the SchedulerHelper.
        """
        self.functions: List[Callable[[], None]] = []
        self.lock = threading.Lock()

    def add_function(self, func: Callable[[], None]) -> None:
        """
        Add a function to be executed at program exit.

        Args:
            func (Callable[[], None]): The function to be added.
        """
        with self.lock:
            self.functions.append(func)

    def run_at_exit(self) -> None:
        """
        Run all registered functions at program exit.
        """
        for func in self.functions:
            func()


scheduler = SchedulerHelper()


def register_scheduler_function(func: Callable[[], None]) -> None:
    """
    Register a function to be executed at program exit using the SchedulerHelper.

    Args:
        func (Callable[[], None]): The function to be registered.
    """
    scheduler.add_function(func)
    atexit.register(scheduler.run_at_exit)
