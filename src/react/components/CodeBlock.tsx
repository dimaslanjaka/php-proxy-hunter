import React, { useEffect, useRef } from 'react';
// We'll dynamically import highlight.js at runtime to avoid bundler-time
// initialization/ordering issues with prebuilt highlighter packages.
import 'highlight.js/styles/github-dark.css';

interface CodeBlockProps {
  language?: string;
  children: string;
  className?: string;
}

const CodeBlock: React.FC<CodeBlockProps> = ({ language = '', children, className = '' }) => {
  const codeRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    let mounted = true;

    (async () => {
      try {
        const hljsModule = await import('highlight.js/lib/core');
        const hljs: any = hljsModule.default || hljsModule;
        // register common languages
        const [javascript, xml, cssLang, scssLang, javaLang, pyLang, tsLang] = await Promise.all([
          import('highlight.js/lib/languages/javascript'),
          import('highlight.js/lib/languages/xml'),
          import('highlight.js/lib/languages/css'),
          import('highlight.js/lib/languages/scss'),
          import('highlight.js/lib/languages/java'),
          import('highlight.js/lib/languages/python'),
          import('highlight.js/lib/languages/typescript')
        ]);

        hljs.registerLanguage('javascript', javascript.default || javascript);
        hljs.registerLanguage('xml', xml.default || xml);
        hljs.registerLanguage('html', xml.default || xml);
        hljs.registerLanguage('css', cssLang.default || cssLang);
        hljs.registerLanguage('scss', scssLang.default || scssLang);
        hljs.registerLanguage('java', javaLang.default || javaLang);
        hljs.registerLanguage('python', pyLang.default || pyLang);
        hljs.registerLanguage('typescript', tsLang.default || tsLang);

        if (!mounted) return;
        const el = codeRef.current;
        if (el) {
          // set raw text to avoid innerHTML issues
          if (el.textContent !== children) el.textContent = children;
          hljs.highlightElement(el as any);
        }
      } catch (err) {
        console.warn('highlight.js dynamic load failed', err);
      }
    })();

    return () => {
      mounted = false;
    };
  }, [children, language]);

  return (
    <div
      className={`rounded-lg p-3 text-xs font-mono overflow-x-auto border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 ${className}`}>
      <pre className="whitespace-pre-wrap font-mono text-xs p-2">
        <code ref={codeRef} className={language ? `language-${language} hljs` : 'hljs'}>
          {children}
        </code>
      </pre>
    </div>
  );
};

export default CodeBlock;
