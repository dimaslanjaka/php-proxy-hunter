import time
import os


class IterationHelper:
    def __init__(self, items, state_file="tmp/data/state.txt"):
        self.items = items
        self.state_file = state_file
        self.ensure_directory_exists()
        self.current_index = self.load_state()

    def ensure_directory_exists(self):
        directory = os.path.dirname(self.state_file)
        if not os.path.exists(directory):
            os.makedirs(directory)

    def save_state(self):
        try:
            with open(self.state_file, "w") as file:
                file.write(str(self.current_index))
        except IOError as e:
            print(f"Error saving state: {e}")

    def load_state(self):
        if os.path.exists(self.state_file):
            try:
                with open(self.state_file, "r") as file:
                    return int(file.read().strip())
            except (ValueError, IOError) as e:
                print(f"Error loading state: {e}")
                return 0
        return 0

    def process_item(self, item):
        print(f"Processing: {item}")
        # Simulate processing time
        time.sleep(1)

    def pause(self, seconds):
        print(f"Pausing for {seconds} seconds...")
        time.sleep(seconds)

    def run(self):
        try:
            for i in range(self.current_index, len(self.items)):
                self.process_item(self.items[i])
                self.current_index = i + 1
                self.save_state()
                self.pause(2)  # Pause for 2 seconds between items
        finally:
            # Save state one last time in case of interruption
            self.save_state()


if __name__ == "__main__":
    items = [f"Item {i}" for i in range(1, 101)]  # Example list of 100 items
    helper = IterationHelper(items)
    helper.run()
