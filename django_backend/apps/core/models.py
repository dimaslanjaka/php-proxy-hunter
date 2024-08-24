import datetime
from typing import Tuple
from django.db import models


class TimestampedModel(models.Model):
    # A timestamp representing when this object was created.
    created_at = models.DateTimeField(auto_now_add=True)

    # A timestamp reprensenting when this object was last updated.
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True

        # By default, any model that inherits from `TimestampedModel` should
        # be ordered in reverse-chronological order. We can override this on a
        # per-model basis as needed, but reverse-chronological is a good
        # default ordering for most models.
        ordering = ["-created_at", "-updated_at"]


class ProcessStatus(models.Model):
    """
    Model to track the status of various processes to ensure they run only once.

    Attributes:
        process_name (str): The unique name of the process.
        is_done (bool): Boolean flag indicating whether the process is completed.
        timestamp (datetime.datetime): The timestamp of the last update to the process status.

    Example usage:
        # Check if the process has already been completed
        process_status, created = ProcessStatus.objects.get_or_create(process_name="my_unique_process")

        if process_status.is_done:
            print("Process already completed. Skipping.")
        else:
            # Run the process
            print("Running the process...")
            # (Your process code here)

            # Mark the process as done
            process_status.is_done = True
            process_status.save()
            print("Process completed and marked as done.")
    """

    process_name: str = models.CharField(max_length=255, unique=True)
    is_done: bool = models.BooleanField(default=False)
    timestamp: "datetime.datetime" = models.DateTimeField(auto_now=True)

    def __str__(self) -> str:
        """
        Returns a string representation of the ProcessStatus instance.

        Returns:
            str: A string that shows the process name and its completion status.
        """
        return f"{self.process_name}: {'Done' if self.is_done else 'Not Done'}"

    @classmethod
    def get_or_create_status(cls, process_name: str) -> Tuple["ProcessStatus", bool]:
        """
        Retrieves an existing ProcessStatus or creates a new one if it doesn't exist.

        Args:
            process_name (str): The name of the process to retrieve or create.

        Returns:
            Tuple[ProcessStatus, bool]: A tuple containing the ProcessStatus instance and a
                                        boolean indicating if it was created (True) or retrieved (False).
        """
        return cls.objects.get_or_create(process_name=process_name)
