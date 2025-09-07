import React from 'react';

/**
 * Props for the Modal component.
 */
/**
 * Props for the Modal component.
 *
 * @property show Controls the visibility of the modal.
 * @property onClose Function to call when the modal is closed.
 * @property title Optional title to display in the modal header.
 * @property children Content to display inside the modal body.
 * @property actions Optional actions (e.g., buttons) to display in the modal footer.
 * @property size Modal size, defaults to 'xl'. Accepts 'sm', 'md', 'lg', 'xl', '2xl', '3xl'.
 * @property scrollable If true, modal body is scrollable (for long forms). Default: false.
 */
interface ModalProps {
  show: boolean;
  onClose: () => void;
  title?: React.ReactNode;
  children?: React.ReactNode;
  actions?: React.ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl';
  scrollable?: boolean;
}

const sizeClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl'
};

/**
 * Modal component for displaying dialogs, confirmations, or custom content.
 *
 * @param show Controls the visibility of the modal.
 * @param onClose Function to call when the modal is closed.
 * @param title Optional title to display in the modal header.
 * @param children Content to display inside the modal body.
 * @param actions Optional actions (e.g., buttons) to display in the modal footer.
 * @param size Modal size, defaults to 'xl'. Accepts 'sm', 'md', 'lg', 'xl', '2xl', '3xl'.
 * @param scrollable If true, modal body is scrollable (for long forms). Default: false.
 *
 * @example
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
 *   size="2xl"
 *   scrollable
 * >
 *   Are you sure you want to delete this item?
 * </Modal>
 */
const Modal: React.FC<ModalProps> = ({ show, onClose, title, children, actions, size = 'md', scrollable = false }) => {
  if (!show) return null;
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900 bg-opacity-50 overflow-y-auto">
      <div
        className={`relative w-full ${sizeClasses[size] || sizeClasses['xl']} mx-4 my-8 max-h-[90vh] flex items-center justify-center`}>
        <div className="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg flex flex-col w-full max-h-[90vh]">
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
          <div className={scrollable ? 'px-6 py-4 overflow-y-auto flex-1 max-h-[60vh] sm:max-h-[70vh]' : 'px-6 py-4'}>
            {children}
          </div>
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
