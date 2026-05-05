import React from 'react';

type Props = {
  /** px from top under which the button shows the "down" icon; otherwise shows "up" */
  threshold?: number;
};

const FloatingScrollButton: React.FC<Props> = ({ threshold = 250 }) => {
  const [mode, setMode] = React.useState<'down' | 'up'>('down');
  const [visible, setVisible] = React.useState(false);

  React.useEffect(() => {
    const onScroll = () => {
      const doc = document.documentElement;
      const scrollY = window.scrollY || doc.scrollTop || 0;
      const canScroll = doc.scrollHeight > window.innerHeight + 10;
      if (!canScroll) {
        setVisible(false);
        return;
      }
      setVisible(true);
      if (scrollY < threshold) setMode('down');
      else setMode('up');
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
    };
  }, [threshold]);

  const handleClick = () => {
    if (mode === 'down') {
      window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' });
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  if (!visible) return null;

  return (
    <button
      aria-label={mode === 'down' ? 'Scroll to bottom' : 'Scroll to top'}
      title={mode === 'down' ? 'Scroll to bottom' : 'Scroll to top'}
      onClick={handleClick}
      className={
        'fixed z-50 bottom-4 right-4 w-10 h-10 flex items-center justify-center rounded-full shadow-md text-white ' +
        'bg-blue-600/60 hover:bg-blue-600/80 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-transform transform-gpu backdrop-blur-sm ' +
        'dark:bg-blue-500/40 dark:hover:bg-blue-500/60 dark:focus:ring-blue-200'
      }>
      {mode === 'down' ? (
        <i className="fa-light fa-caret-down text-base text-white opacity-100" aria-hidden="true" />
      ) : (
        <i className="fa-light fa-caret-up text-base text-white opacity-100" aria-hidden="true" />
      )}
    </button>
  );
};

export default FloatingScrollButton;
