import React from 'react';

/**
 * Props for the Modal component.
 */
interface ModalProps {
  /** Controls the visibility of the modal. */
  show: boolean;
  /** Function to call when the modal is closed. */
  onClose: () => void;
  /** Optional title to display in the modal header. */
  title?: React.ReactNode;
  /** Content to display inside the modal body. */
  children?: React.ReactNode;
  /** Optional actions (e.g., buttons) to display in the modal footer. */
  actions?: React.ReactNode;
  /** Modal size, defaults to 'md'. */
  size?: 'sm' | 'md' | 'lg' | 'xl';
}

const sizeClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl'
};

/**
 * Modal component for displaying dialogs, confirmations, or custom content.
 *
 * @example
 * // Usage example:
 * <Modal
 *   show={showModal}
 *   onClose={() => setShowModal(false)}
 *   title="Confirm Delete"
 *   actions={
 *     <>
 *       <button onClick={handleDelete} className="btn btn-danger">Delete</button>
 *       <button onClick={() => setShowModal(false)} className="btn">Cancel</button>
 *     </>
 *   }
 * >
 *   Are you sure you want to delete this item?
 * </Modal>
 */
const Modal: React.FC<ModalProps> = ({ show, onClose, title, children, actions, size = 'md' }) => {
  if (!show) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 overflow-y-auto">
      <div className={`relative w-full ${sizeClasses[size]} mx-4 my-8 max-h-[90vh]`}>
        <div className="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg flex flex-col">
          <div className="flex justify-between items-center border-b px-6 py-4 rounded-t-lg">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
            <button
              type="button"
              className="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 text-2xl p-1 rounded-full focus:outline-none focus:ring-2 focus:ring-gray-400"
              onClick={onClose}
              aria-label="Close modal">
              <i className="fa-solid fa-xmark"></i>
            </button>
          </div>
          <div className="px-6 py-4 overflow-y-auto">{children}</div>
          {actions && (
            <div className="flex justify-end gap-2 border-t px-6 py-3 bg-gray-50 dark:bg-gray-700 rounded-b-lg">
              {actions}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default Modal;
