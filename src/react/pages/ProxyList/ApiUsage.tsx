import React from 'react';
import CodeBlock from '../../components/CodeBlock';
import { createUrl } from '../../utils/url';

function ApiUsage() {
  // Use string constants to avoid template literal issues in patch context
  const curlExample =
    'curl -X POST \\\n  -d "proxy=1.2.3.4:8080&type=http" \\\n  "' + createUrl('/php_backend/proxy-checker.php') + '"';
  const curlAuthExample =
    'curl -X POST \\\n  -d "proxy=1.2.3.4:8080&type=http&username=myuser&password=mypass" \\\n  "' +
    createUrl('/php_backend/proxy-checker.php') +
    '"';
  const responseExample =
    '{\n  "error": false,\n  "message": "Proxy check is in progress. Please check back later.",\n  "logEmbedUrl": "...",\n  "statusEmbedUrl": "..."\n}';
  return (
    <section className="my-8">
      <div className="p-6 rounded-xl border border-blue-200 dark:border-blue-700 bg-gradient-to-br from-blue-50 to-white dark:from-blue-900/60 dark:to-gray-900 shadow-md dark:shadow-white transition-colors duration-300">
        <h2 className="flex items-center gap-2 text-lg font-bold mb-3 text-blue-800 dark:text-blue-200">
          <i className="fa-duotone fa-terminal"></i> Proxy Checker API Usage
        </h2>
        <p className="mb-2 text-gray-700 dark:text-gray-200">
          You can check a proxy programmatically using the following API endpoint:
        </p>
        <div className="mb-3">
          <span className="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-100 dark:bg-blue-800 text-blue-900 dark:text-blue-100 text-xs font-mono border border-blue-200 dark:border-blue-700">
            POST
            <span className="font-semibold">{createUrl('/php_backend/proxy-checker.php')}</span>
          </span>
        </div>
        <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">Parameters (form-urlencoded):</p>
        <ul className="list-disc pl-6 text-gray-700 dark:text-gray-200 text-sm mb-3 space-y-1">
          <li>
            <span className="font-semibold">proxy</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(required)</span>: Proxy string (e.g.,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">1.2.3.4:8080</code>)
          </li>
          <li>
            <span className="font-semibold">type</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(optional)</span>: Proxy type (
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">http</code>,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">https</code>,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">socks4</code>,{' '}
            <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">socks5</code>)
          </li>
          <li>
            <span className="font-semibold">username</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(optional)</span>: Username for proxy auth
          </li>
          <li>
            <span className="font-semibold">password</span>{' '}
            <span className="text-xs text-blue-700 dark:text-blue-300">(optional)</span>: Password for proxy auth
          </li>
        </ul>
        <div className="mb-2">
          <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">
            Example <b>curl</b> request:
          </p>
          <CodeBlock language="bash">{curlExample}</CodeBlock>
          <p className="mb-1 mt-4 text-gray-700 dark:text-gray-200 font-semibold">
            Example with <b>username</b> and <b>password</b>:
          </p>
          <CodeBlock language="bash">{curlAuthExample}</CodeBlock>
        </div>
        <div>
          <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">Response:</p>
          <CodeBlock language="json">{responseExample}</CodeBlock>
        </div>
        <p className="mt-3 text-gray-600 dark:text-gray-300 text-xs">
          Use the <span className="font-semibold">logEmbedUrl</span> and{' '}
          <span className="font-semibold">statusEmbedUrl</span> from the response to poll for log and status updates.
        </p>
      </div>
    </section>
  );
}

export default ApiUsage;
