import React from 'react';
import CodeBlock from '../../components/CodeBlock';
// The project already imports highlight.js inside CodeBlock; keep style consistent

const sampleJs = `// Example: simple fetch wrapper
async function fetchJson(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error('Network error');
  return res.json();
}

fetchJson('/api/status')
  .then(console.log)
  .catch(console.error);
`;

export default function HighlightJS() {
  return (
    <div className="p-6">
      <div className="max-w-4xl mx-auto text-gray-900 dark:text-gray-100">
        <h1 className="text-2xl font-bold mb-4 text-gray-900 dark:text-gray-100">Highlight.js example</h1>
        <p className="mb-4 text-sm text-gray-600 dark:text-gray-300">
          This page demonstrates{' '}
          <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded text-sm text-gray-900 dark:text-gray-100">
            CodeBlock
          </code>{' '}
          component using highlight.js — toggle the site theme to see dark/light highlight themes.
        </p>
        <div className="bg-white dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
          <CodeBlock language="javascript">{sampleJs}</CodeBlock>
        </div>
      </div>
    </div>
  );
}
