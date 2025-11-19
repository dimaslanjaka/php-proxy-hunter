import React, { useEffect, useRef } from 'react';
import 'highlight.js/styles/github-dark.css';

interface CodeBlockProps {
  language?: string;
  children: string;
  className?: string;
}

/**
 * Vite-safe language loader map.
 * Every entry must explicitly reference a real file.
 */
const languageLoaders: Record<string, () => Promise<any>> = {
  bash: () => import('highlight.js/lib/languages/bash'),
  sh: () => import('highlight.js/lib/languages/bash'),
  shell: () => import('highlight.js/lib/languages/bash'),
  zsh: () => import('highlight.js/lib/languages/bash'),

  javascript: () => import('highlight.js/lib/languages/javascript'),
  js: () => import('highlight.js/lib/languages/javascript'),
  jsx: () => import('highlight.js/lib/languages/javascript'),

  typescript: () => import('highlight.js/lib/languages/typescript'),
  ts: () => import('highlight.js/lib/languages/typescript'),
  tsx: () => import('highlight.js/lib/languages/typescript'),
  mts: () => import('highlight.js/lib/languages/typescript'),
  cts: () => import('highlight.js/lib/languages/typescript'),

  python: () => import('highlight.js/lib/languages/python'),
  py: () => import('highlight.js/lib/languages/python'),

  xml: () => import('highlight.js/lib/languages/xml'),
  html: () => import('highlight.js/lib/languages/xml'),

  yaml: () => import('highlight.js/lib/languages/yaml'),
  yml: () => import('highlight.js/lib/languages/yaml'),

  markdown: () => import('highlight.js/lib/languages/markdown'),
  md: () => import('highlight.js/lib/languages/markdown'),

  json: () => import('highlight.js/lib/languages/json'),
  css: () => import('highlight.js/lib/languages/css'),
  scss: () => import('highlight.js/lib/languages/scss'),

  powershell: () => import('highlight.js/lib/languages/powershell'),
  ps: () => import('highlight.js/lib/languages/powershell'),
  ps1: () => import('highlight.js/lib/languages/powershell'),

  ruby: () => import('highlight.js/lib/languages/ruby'),
  rb: () => import('highlight.js/lib/languages/ruby'),
  gemspec: () => import('highlight.js/lib/languages/ruby'),
  podspec: () => import('highlight.js/lib/languages/ruby'),
  thor: () => import('highlight.js/lib/languages/ruby'),
  irb: () => import('highlight.js/lib/languages/ruby'),

  dos: () => import('highlight.js/lib/languages/dos'),
  bat: () => import('highlight.js/lib/languages/dos'),
  cmd: () => import('highlight.js/lib/languages/dos')
};

/**
 * Loads & registers a single language safely
 */
async function loadLanguage(hljs: any, rawLang: string) {
  const lang = rawLang.replace(/^language-/, '').toLowerCase();
  if (!lang) return;

  // skip if already loaded
  if (hljs.getLanguage?.(lang)) return;

  const loader = languageLoaders[lang];
  if (!loader) return;

  try {
    const mod = await loader();
    hljs.registerLanguage(lang, mod.default || mod);
  } catch (err) {
    console.warn(`Failed to load HLJS language '${lang}'`, err);
  }
}

const CodeBlock: React.FC<CodeBlockProps> = ({ language = '', children, className = '' }) => {
  const codeRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (typeof window === 'undefined') return; // SSR guard

    let active = true;

    (async () => {
      const core = await import('highlight.js/lib/core');
      const hljs: any = core.default || core;

      if (language) {
        await loadLanguage(hljs, language);
      }

      if (!active) return;

      const el = codeRef.current;
      if (!el) return;

      // avoid DOM thrashing
      if (el.textContent !== children) {
        el.textContent = children;
      }

      hljs.highlightElement(el);
    })();

    return () => {
      active = false;
    };
  }, [children, language]);

  return (
    <div
      className={`rounded-lg text-xs font-mono overflow-x-auto border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 ${className}`}>
      <pre className="whitespace-pre-wrap font-mono text-xs p-2">
        <code ref={codeRef} className={language ? `language-${language} hljs` : 'hljs'}>
          {children}
        </code>
      </pre>
    </div>
  );
};

export default CodeBlock;
