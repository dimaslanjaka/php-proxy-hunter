// source: https://github.com/rahul5522/remove-node-child-detector

/**
 * Default options for the remove-node-child-detector debugger utility.
 * @typedef {Object} RemoveNodeChildDetectorOptions
 * @property {boolean} highlightErrorNode - Whether to visually highlight problematic nodes.
 * @property {boolean} logErrorNodeToConsole - Whether to log errors to the console.
 * @property {boolean} showErrorAlerts - Whether to show alert popups on errors.
 */
const defaultOptions = {
  highlightErrorNode: true,
  logErrorNodeToConsole: true,
  showErrorAlerts: false
};

/**
 * Visually highlights a DOM element by applying a border and background color.
 * @param {Element} element - The DOM element to highlight.
 */
function highlightElement(element) {
  if (element && element.style) {
    element.style.border = `0.5px solid tomato`;
    element.style.backgroundColor = 'rgba(255, 0, 0, 0.1)';
  }
}

/**
 * Logs an error message to the console.
 * @param {string} message - The error message.
 * @param {...any} args - Additional arguments to log.
 */
function logError(message, ...args) {
  if (console && console.error) {
    console.error(`${message}`, ...args);
  }
}

/**
 * Shows an alert popup with the given message.
 * @param {string} message - The message to display.
 */
function showAlert(message) {
  if (typeof window !== 'undefined' && window.alert) {
    window.alert(`${message}`);
  }
}

/**
 * Overrides Node.prototype.removeChild to detect and highlight invalid DOM removals.
 * @param {RemoveNodeChildDetectorOptions} options - Debugger options.
 */
function overrideRemoveChild(options) {
  const originalRemoveChild = Node.prototype.removeChild;
  Node.prototype.removeChild = function (child) {
    if (child.parentNode !== this) {
      if (options.logErrorNodeToConsole) {
        logError('Cannot remove a child from a different parent', child, this);
      }
      if (options.showErrorAlerts) {
        showAlert(`Cannot remove a child from a different parent ${child}`);
      }
      if (options.highlightErrorNode) {
        highlightElement(child);
        highlightElement(this);
      }
      return child;
    }
    return originalRemoveChild.apply(this, arguments);
  };
}

/**
 * Overrides Node.prototype.insertBefore to detect and highlight invalid DOM insertions.
 * @param {RemoveNodeChildDetectorOptions} options - Debugger options.
 */
function overrideInsertBefore(options) {
  const originalInsertBefore = Node.prototype.insertBefore;
  Node.prototype.insertBefore = function (newNode, referenceNode) {
    if (referenceNode && referenceNode.parentNode !== this) {
      if (options.logErrorNodeToConsole) {
        logError('Cannot insert before a reference node from a different parent', referenceNode, this);
      }
      if (options.showErrorAlerts) {
        showAlert(`Cannot insert before a reference node from a different parent`);
      }
      if (options.highlightErrorNode) {
        highlightElement(referenceNode);
        highlightElement(this);
      }
      return newNode;
    }
    return originalInsertBefore.apply(this, arguments);
  };
}

/**
 * Starts the remove-node-child-detector debugger utility.
 * Call this function in your app entry to enable DOM operation debugging.
 * @param {Partial<RemoveNodeChildDetectorOptions>} [userOptions] - Optional overrides for default options.
 */
function startDebugger(userOptions = {}) {
  const options = { ...defaultOptions, ...userOptions };

  if (typeof window === 'undefined' || typeof Node === 'undefined') {
    console.warn('This debugger is intended for browser environments only.');
    return;
  }

  if (typeof Node === 'function' && Node.prototype) {
    overrideRemoveChild(options);
    overrideInsertBefore(options);
  } else {
    logError('Node.prototype is not available. Debugging cannot be enabled.');
  }
}

/**
 * @module remove-node-child-detector
 */
export default startDebugger;
