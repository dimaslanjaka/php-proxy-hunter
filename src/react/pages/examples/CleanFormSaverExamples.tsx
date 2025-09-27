import React, { useRef } from 'react';
// Import from your actual jquery-form-saver React implementation
import { ReactFormSaver, useFormSaver, type ReactFormSaverRef } from 'jquery-form-saver/react';

/**
 * Example showing how to integrate the React Form Saver
 * into the existing php-proxy-hunter React application
 */

// Example 1: Using in Settings page (similar to existing Settings.tsx)
export const SettingsWithFormSaver: React.FC = () => {
  const formSaverRef = useRef<ReactFormSaverRef>(null);
  const [username, setUsername] = React.useState('');
  const [email, setEmail] = React.useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      // Submit form logic here...
      console.log('Form submitted successfully');

      // Clear saved values after successful submission
      formSaverRef.current?.clearForm();
    } catch (error) {
      console.error('Form submission failed:', error);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-100 dark:bg-gray-900 mt-4">
      <ReactFormSaver
        ref={formSaverRef}
        debug={process.env.NODE_ENV === 'development'}
        className="bg-white dark:bg-gray-800 p-8 rounded shadow-md w-full max-w-md"
        onSave={(element) => console.log('Saved field:', element.getAttribute('name'))}>
        <h2 className="text-2xl font-bold mb-6 text-center text-blue-600 dark:text-white">Settings with Auto-Save</h2>

        <div className="mb-4">
          <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="username">
            Username
          </label>
          <input
            id="username"
            type="text"
            name="username"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
            placeholder="Enter username"
          />
        </div>

        <div className="mb-4">
          <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="email">
            Email
          </label>
          <input
            id="email"
            type="email"
            name="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
            placeholder="Enter email"
          />
        </div>

        <div className="mb-4">
          <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="password">
            Password
          </label>
          <input
            id="password"
            type="password"
            name="password"
            no-save="true"
            className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
            placeholder="Enter password (not saved)"
          />
        </div>

        <button
          type="submit"
          onClick={handleSubmit}
          className="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
          Save Changes
        </button>
      </ReactFormSaver>
    </div>
  );
};

// Example 2: Using with hook for more control
export const ContactFormWithHook: React.FC = () => {
  const { formRef, clearForm } = useFormSaver({
    debug: process.env.NODE_ENV === 'development',
    storagePrefix: '/contact-form'
  });

  const [submitted, setSubmitted] = React.useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget as HTMLFormElement);

    try {
      // Submit form logic here...
      console.log('Contact form submitted:', Object.fromEntries(formData));

      setSubmitted(true);
      clearForm(); // Clear saved values after successful submission
    } catch (error) {
      console.error('Submission failed:', error);
    }
  };

  return (
    <div className="max-w-md mx-auto mt-8 p-6 bg-white dark:bg-gray-800 rounded shadow-md">
      <h2 className="text-2xl font-bold mb-6 text-center text-blue-600 dark:text-white">Contact Form</h2>

      {submitted ? (
        <div className="text-center text-green-600">
          <p>Thank you! Your message has been sent.</p>
          <button onClick={() => setSubmitted(false)} className="mt-4 text-blue-600 hover:underline">
            Send another message
          </button>
        </div>
      ) : (
        <form ref={formRef} onSubmit={handleSubmit}>
          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="contact-name">
              Name
            </label>
            <input
              id="contact-name"
              type="text"
              name="name"
              required
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
              placeholder="Your name"
            />
          </div>

          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="contact-email">
              Email
            </label>
            <input
              id="contact-email"
              type="email"
              name="email"
              required
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
              placeholder="your@email.com"
            />
          </div>

          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="contact-subject">
              Subject
            </label>
            <select
              id="contact-subject"
              name="subject"
              required
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300">
              <option value="">Select Subject</option>
              <option value="general">General Inquiry</option>
              <option value="support">Technical Support</option>
              <option value="feedback">Feedback</option>
              <option value="bug">Bug Report</option>
            </select>
          </div>

          <div className="mb-4">
            <label className="block mb-1 text-gray-700 dark:text-gray-200" htmlFor="contact-message">
              Message
            </label>
            <textarea
              id="contact-message"
              name="message"
              rows={4}
              required
              className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
              placeholder="Your message..."
            />
          </div>

          <div className="mb-6">
            <label className="flex items-center text-gray-700 dark:text-gray-200">
              <input type="checkbox" name="newsletter" className="mr-2" />
              Subscribe to our newsletter
            </label>
          </div>

          <div className="flex gap-2">
            <button
              type="submit"
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
              Send Message
            </button>
            <button
              type="button"
              onClick={clearForm}
              className="bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 px-4 rounded">
              Clear Draft
            </button>
          </div>
        </form>
      )}
    </div>
  );
};

// Example 3: Advanced usage with dynamic fields
export const DynamicFormExample: React.FC = () => {
  const { formRef, saveForm } = useFormSaver({ debug: true });
  const [items, setItems] = React.useState([{ id: 1, name: '', description: '' }]);

  const addItem = () => {
    setItems((prev) => [...prev, { id: Date.now(), name: '', description: '' }]);
  };

  const removeItem = (id: number) => {
    setItems((prev) => prev.filter((item) => item.id !== id));
  };

  return (
    <div className="max-w-lg mx-auto mt-8 p-6 bg-white dark:bg-gray-800 rounded shadow-md">
      <h2 className="text-2xl font-bold mb-6 text-center text-blue-600 dark:text-white">Dynamic Form Example</h2>

      <form ref={formRef}>
        {items.map((item, index) => (
          <div key={item.id} className="mb-4 p-4 border rounded bg-gray-50 dark:bg-gray-700">
            <div className="flex justify-between items-center mb-2">
              <h3 className="font-semibold text-gray-700 dark:text-gray-200">Item {index + 1}</h3>
              {items.length > 1 && (
                <button type="button" onClick={() => removeItem(item.id)} className="text-red-600 hover:text-red-800">
                  Remove
                </button>
              )}
            </div>

            <div className="mb-2">
              <input
                type="text"
                name={`item_${item.id}_name`}
                placeholder="Item name"
                className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
              />
            </div>

            <div>
              <textarea
                name={`item_${item.id}_description`}
                placeholder="Item description"
                rows={2}
                className="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300"
              />
            </div>
          </div>
        ))}

        <div className="flex gap-2">
          <button
            type="button"
            onClick={addItem}
            className="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded">
            Add Item
          </button>
          <button
            type="button"
            onClick={saveForm}
            className="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">
            Save Draft
          </button>
        </div>
      </form>

      <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">
        This form automatically saves your input as you type. Try refreshing the page!
      </p>
    </div>
  );
};

export default {
  SettingsWithFormSaver,
  ContactFormWithHook,
  DynamicFormExample
};
