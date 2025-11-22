import React from 'react';
import CodeBlock from '../../components/CodeBlock';
import { createUrl } from '../../utils/url';

function ApiUsage() {
  // Use string constants to avoid template literal issues in patch context
  const httpCurl = `curl -X POST \\
  -d "proxy=1.2.3.4:8080" \\
  "${createUrl('/php_backend/check-http-proxy.php')}"`;
  const httpCurlInlineAuth = `curl -X POST \\
  -d "proxy=31.43.179.219:80@user:pass" \\
  "${createUrl('/php_backend/check-http-proxy.php')}"`;
  const httpResponse = `{
  "error": false,
  "message": "[HTTP] Proxy check initiated.",
  "logFile": "${createUrl('/php_backend/logs.php')}?hash=check-http-proxy/encoded-uid"
}`;

  const httpsCurl = `curl -X POST \\
  -d "proxy=1.2.3.4:8080" \\
  "${createUrl('/php_backend/check-https-proxy.php')}"`;
  const httpsCurlInlineAuth = `curl -X POST \\
  -d "proxy=31.43.179.219:80@user:pass" \\
  "${createUrl('/php_backend/check-https-proxy.php')}"`;
  const httpsResponse = `{
  "error": false,
  "message": "[HTTPS] Proxy check initiated.",
  "logFile": "${createUrl('/php_backend/logs.php')}?hash=check-https-proxy/encoded-uid"
}`;
  return (
    <section className="my-8">
      <div className="p-6 rounded-xl border border-blue-200 dark:border-blue-700 bg-gradient-to-br from-blue-50 to-white dark:from-blue-900/60 dark:to-gray-900 shadow-md dark:shadow-white transition-colors duration-300">
        <h2 className="flex items-center gap-2 text-lg font-bold mb-3 text-blue-800 dark:text-blue-200">
          <i className="fa-duotone fa-terminal"></i> Proxy Checker API Usage
        </h2>
        <p className="mb-2 text-gray-700 dark:text-gray-200">
          You can check a proxy programmatically using the following API:
        </p>
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
          {/* username/password parameters removed — use inline auth format (host:port@user:pass) */}
        </ul>
        <div className="mb-2">
          <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div>
              <h3 className="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-200">HTTP checker</h3>
              <p className="text-xs text-gray-600 dark:text-gray-300 mb-2">
                POST {createUrl('/php_backend/check-http-proxy.php')}
              </p>
              <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">Example (no auth):</p>
              <CodeBlock language="bash">{httpCurl}</CodeBlock>
              <p className="mb-1 mt-3 text-gray-700 dark:text-gray-200 font-semibold">
                Example (auth - inline format):
              </p>
              <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Inline auth format:{' '}
                <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">31.43.179.219:80@user:pass</code>
              </p>
              <CodeBlock language="bash">{httpCurlInlineAuth}</CodeBlock>
              <p className="mt-3 text-gray-700 dark:text-gray-200 text-xs font-semibold">Response:</p>
              <CodeBlock language="json">{httpResponse}</CodeBlock>
            </div>
            <div>
              <h3 className="text-sm font-semibold mb-2 text-gray-800 dark:text-gray-200">HTTPS checker</h3>
              <p className="text-xs text-gray-600 dark:text-gray-300 mb-2">
                POST {createUrl('/php_backend/check-https-proxy.php')}
              </p>
              <p className="mb-1 text-gray-700 dark:text-gray-200 font-semibold">Example (no auth):</p>
              <CodeBlock language="bash">{httpsCurl}</CodeBlock>
              <p className="mb-1 mt-3 text-gray-700 dark:text-gray-200 font-semibold">
                Example (auth - inline format):
              </p>
              <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                Inline auth format:{' '}
                <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded">31.43.179.219:80@user:pass</code>
              </p>
              <CodeBlock language="bash">{httpsCurlInlineAuth}</CodeBlock>
              <p className="mt-3 text-gray-700 dark:text-gray-200 text-xs font-semibold">Response:</p>
              <CodeBlock language="json">{httpsResponse}</CodeBlock>
            </div>
          </div>
        </div>
        <p className="mt-3 text-gray-600 dark:text-gray-300 text-xs">
          The HTTP and HTTPS checkers run as background processes and write per-user logs to the shared logs folder.
          Call the appropriate endpoint and poll the returned log file (or use the Log Viewer UI) for progress.
        </p>
      </div>
    </section>
  );
}

export default ApiUsage;
