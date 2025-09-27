import React from 'react';
import { SettingsWithFormSaver, ContactFormWithHook, DynamicFormExample } from './examples/CleanFormSaverExamples';

/**
 * Simple wrapper for Form Saver examples
 *
 * This is a minimal wrapper that displays all three examples in a single page.
 * Perfect for testing or demonstration purposes.
 *
 * Add to your routes:
 * <Route path="/form-saver" element={<SimpleFormSaverDemo />} />
 */
const SimpleFormSaverDemo: React.FC = () => {
  return (
    <div className="container mx-auto px-4 py-8 space-y-12">
      {/* Page Header */}
      <div className="text-center mb-12">
        <h1 className="text-4xl font-bold text-gray-900 dark:text-white mb-4">React Form Saver Demo</h1>
        <p className="text-lg text-gray-600 dark:text-gray-400 max-w-3xl mx-auto">
          Demonstration of automatic form saving functionality. All form values are automatically saved to localStorage
          and restored when you refresh the page or navigate back.
        </p>
      </div>

      {/* Example 1: Settings Form */}
      <section>
        <div className="mb-6">
          <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
            1. Settings Form (Component Example)
          </h2>
          <p className="text-gray-600 dark:text-gray-400">
            Using the <code className="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">ReactFormSaver</code> component
            wrapper
          </p>
        </div>
        <SettingsWithFormSaver />
      </section>

      <hr className="border-gray-200 dark:border-gray-700" />

      {/* Example 2: Contact Form */}
      <section>
        <div className="mb-6">
          <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">2. Contact Form (Hook Example)</h2>
          <p className="text-gray-600 dark:text-gray-400">
            Using the <code className="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">useFormSaver</code> hook for more
            control
          </p>
        </div>
        <ContactFormWithHook />
      </section>

      <hr className="border-gray-200 dark:border-gray-700" />

      {/* Example 3: Dynamic Form */}
      <section>
        <div className="mb-6">
          <h2 className="text-2xl font-semibold text-gray-900 dark:text-white mb-2">
            3. Dynamic Form (Advanced Example)
          </h2>
          <p className="text-gray-600 dark:text-gray-400">
            Demonstrating auto-save with dynamically added/removed form fields
          </p>
        </div>
        <DynamicFormExample />
      </section>

      {/* Footer */}
      <div className="text-center pt-12 border-t border-gray-200 dark:border-gray-700">
        <p className="text-sm text-gray-500 dark:text-gray-400">
          Try filling out the forms above and refreshing the page to see the auto-save functionality in action!
        </p>
      </div>
    </div>
  );
};

export default SimpleFormSaverDemo;
