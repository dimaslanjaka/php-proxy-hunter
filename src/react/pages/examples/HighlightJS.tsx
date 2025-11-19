import React from 'react';
import CodeBlock from '../../components/CodeBlock';
// The project already imports highlight.js inside CodeBlock; keep style consistent

const samples: { lang: string; code: string; title?: string }[] = [
  {
    lang: 'javascript',
    title: 'JavaScript',
    code: `// Example: simple fetch wrapper
async function fetchJson(url) {
  const res = await fetch(url);
  if (!res.ok) throw new Error('Network error');
  return res.json();
}

fetchJson('/api/status').then(console.log).catch(console.error);
`
  },
  {
    lang: 'typescript',
    title: 'TypeScript',
    code: `type User = { id: number; name: string };
const users: User[] = [];
function addUser(u: User) { users.push(u); }
`
  },
  {
    lang: 'python',
    title: 'Python',
    code: `def greet(name):
    return f"Hello, {name}"

print(greet('world'))
`
  },
  {
    lang: 'bash',
    title: 'Bash',
    code: `#!/usr/bin/env bash
echo "Starting..."
ls -la
`
  },
  {
    lang: 'powershell',
    title: 'PowerShell',
    code: `Write-Output "Hello from PowerShell"
Get-Process | Select-Object -First 5
`
  },
  {
    lang: 'ruby',
    title: 'Ruby',
    code: `puts 'hello'
3.times { |i| puts i }
`
  },
  {
    lang: 'json',
    title: 'JSON',
    code: `{
  "name": "example",
  "version": "1.0.0"
}
`
  },
  {
    lang: 'css',
    title: 'CSS',
    code: `body { font-family: system-ui; }
.btn { background: #06f; color: white; padding: 8px 12px; }
`
  },
  {
    lang: 'scss',
    title: 'SCSS',
    code: `$primary: #06f;
.btn { background: $primary; }
`
  },
  {
    lang: 'xml',
    title: 'HTML/XML',
    code: `<div class="card"><h1>Title</h1></div>
`
  },
  {
    lang: 'yaml',
    title: 'YAML',
    code: `name: example
version: 1.0.0
`
  },
  {
    lang: 'markdown',
    title: 'Markdown',
    code: `# Heading

Some *italic* and **bold** text.
`
  },
  {
    lang: 'dos',
    title: 'DOS / Batch',
    code: `@echo off
echo Hello from batch file
`
  }
];

export default function HighlightJS() {
  return (
    <div className="p-6">
      <div className="max-w-4xl mx-auto text-gray-900 dark:text-gray-100">
        <h1 className="text-2xl font-bold mb-4">Highlight.js example</h1>
        <p className="mb-4 text-sm text-gray-600 dark:text-gray-300">
          This page demonstrates the{' '}
          <code className="bg-gray-100 dark:bg-gray-800 px-1 rounded text-sm">CodeBlock</code> component with several
          languages.
        </p>

        {samples.map((s) => (
          <section
            key={s.lang}
            className="mb-6 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <h2 className="text-lg font-semibold mb-2">{s.title || s.lang}</h2>
            <CodeBlock language={s.lang}>{s.code}</CodeBlock>
          </section>
        ))}
      </div>
    </div>
  );
}
