import React, { useEffect, useRef } from 'react';
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css'; // You can change the style as needed

interface CodeBlockProps {
  language?: string;
  children: string;
  className?: string;
}

const CodeBlock: React.FC<CodeBlockProps> = ({ language = '', children, className = '' }) => {
  const codeRef = useRef<HTMLElement>(null);

  useEffect(() => {
    if (codeRef.current) {
      // Unset previous highlight to avoid warning
      if (codeRef.current.dataset.highlighted) {
        delete codeRef.current.dataset.highlighted;
      }
      hljs.highlightElement(codeRef.current);
    }
  }, [children, language]);

  return (
    <pre
      className={`rounded-lg p-3 text-xs font-mono overflow-x-auto border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 ${className}`}>
      <code ref={codeRef} className={language ? `language-${language}` : ''}>
        {children}
      </code>
    </pre>
  );
};

export default CodeBlock;
