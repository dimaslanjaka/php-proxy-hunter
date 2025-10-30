import Link from '../components/Link';

const About = () => (
  <>
    <main className="min-h-screen flex flex-col items-center justify-center px-4 py-8 max-w-2xl mx-auto">
      <section className="w-full bg-white dark:bg-gray-900 rounded-lg shadow-lg p-6 md:p-10 border border-gray-200 dark:border-gray-700">
        <h2 className="text-3xl font-bold mb-4 text-gray-900 dark:text-white text-center">About PHP Proxy Hunter</h2>
        <p className="mb-6 text-center text-gray-700 dark:text-gray-300">
          <span className="font-semibold text-gray-900 dark:text-white">PHP Proxy Hunter</span> is a cross-platform
          toolkit for proxy checking, hunting, and extraction.
          <br />
          Built with PHP, Python, Node.js, and shell scripting.
          <br />
          It supports HTTP/HTTPS, SOCKS4/5 proxies, CIDR range scanning, open port scanning, and more.
          <br />
          The toolkit offers both web-based and CLI interfaces, supports multithreaded and single-threaded checking.
          <br />
          Compatible with Linux and Windows 10+.
        </p>
        <div className="mb-6">
          <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Key Features</h3>
          <ul className="list-disc list-inside space-y-1 text-left text-gray-700 dark:text-gray-300">
            <li>Proxy checker, hunter, and extractor</li>
            <li>CIDR range and open port scanner</li>
            <li>HTTP/HTTPS, SOCKS4/5 support</li>
            <li>Web and CLI interfaces</li>
            <li>Multithreaded and single-threaded operation</li>
            <li>WhatsApp Bot proxy manager (Ubuntu 20.x)</li>
            <li>Cross-platform: Linux &amp; Windows 10+</li>
            <li>PHP, Python, Bash, Batch support</li>
          </ul>
        </div>
        <div className="mb-6">
          <span className="font-semibold text-gray-900 dark:text-white">Live Demo:</span>{' '}
          <Link
            href="/proxy"
            className="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-200 transition-colors"
            target="_blank"
            rel="noopener noreferrer">
            Proxy Manager Web UI
          </Link>
        </div>
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
              href="mailto:superuser@webmanajemen.com"
              className="text-blue-600 dark:text-blue-400 underline hover:text-blue-800 dark:hover:text-blue-200 transition-colors">
              superuser@webmanajemen.com
            </a>
          </div>
        </div>
      </section>
    </main>
  </>
);

export default About;
