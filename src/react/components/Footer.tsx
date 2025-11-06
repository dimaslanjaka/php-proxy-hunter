import React from 'react';
import LanguageSwitcher from './LanguageSwitcher';

export interface FooterProps {
  /** Optional additional class names for the footer */
  className?: string;
  /** Left/top content area - can contain long text or links */
  children?: React.ReactNode;
  /** Whether to show the default reCAPTCHA branding line */
  showRecaptchaBranding?: boolean;
}

/**
 * Responsive Footer
 * - Designed for long content footers
 * - LanguageSwitcher is shown to the right on wide screens and stacked on small screens
 */
const Footer: React.FC<FooterProps> = ({ className = '', children, showRecaptchaBranding = true }) => {
  return (
    <footer className={`w-full bg-gray-100 dark:bg-gray-900 py-8 mt-16 ${className}`} role="contentinfo">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {/* Grid layout: content spans majority, controls (language) on the side for large screens */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start text-gray-500 dark:text-gray-400">
          <div className="lg:col-span-9">
            <div className="prose prose-sm dark:prose-invert text-sm">
              {/* main children slot for long footer content */}
              {children ? (
                children
              ) : (
                <>
                  <div className="mb-2">
                    <span className="font-medium">&copy; {new Date().getFullYear()} DX. All rights reserved.</span>
                  </div>
                  {showRecaptchaBranding && (
                    <p className="text-xs max-w-2xl">
                      This site is protected by reCAPTCHA and the Google{' '}
                      <a
                        className="underline"
                        href="https://policies.google.com/privacy"
                        target="_blank"
                        rel="noopener noreferrer">
                        Privacy Policy
                      </a>{' '}
                      and{' '}
                      <a
                        className="underline"
                        href="https://policies.google.com/terms"
                        target="_blank"
                        rel="noopener noreferrer">
                        Terms of Service
                      </a>{' '}
                      apply.
                    </p>
                  )}
                </>
              )}
            </div>
          </div>

          <div className="lg:col-span-3 flex lg:justify-end">
            {/* place language switcher in a compact container */}
            <div className="w-full max-w-xs lg:max-w-none">
              <LanguageSwitcher />
            </div>
          </div>
        </div>
      </div>
    </footer>
  );
};

export default Footer;
