const Contact = () => (
  <>
    <main className="min-h-screen flex flex-col items-center justify-center px-4 py-8 max-w-2xl mx-auto">
      <section className="w-full bg-white dark:bg-gray-900 rounded-lg shadow-lg p-6 md:p-10 border border-gray-200 dark:border-gray-700">
        <h2 className="text-3xl font-bold mb-4 text-gray-900 dark:text-white text-center">Contact</h2>
        <p className="mb-6 text-center text-gray-700 dark:text-gray-300">
          For questions, feedback, or support regarding PHP Proxy Hunter, please use the contact information below.
        </p>
        <div className="w-full text-center text-sm text-gray-500 dark:text-gray-400">
          <div className="mb-1 font-semibold text-gray-900 dark:text-white">Author:</div>
          <div className="mb-1 text-gray-900 dark:text-white">Dimas Lanjaka</div>
          <div className="mb-1">
            <a
              href="https://www.webmanajemen.com"
              className="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-200 transition-colors"
              target="_blank"
              rel="noopener noreferrer">
              webmanajemen.com
            </a>
          </div>
          <div>
            <a
              href="mailto:dimaslanjaka@gmail.com"
              className="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-200 transition-colors">
              dimaslanjaka@gmail.com
            </a>
          </div>
        </div>
      </section>
    </main>
  </>
);

export default Contact;
