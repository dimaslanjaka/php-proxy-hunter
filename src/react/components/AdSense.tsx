import { useEffect, useRef, type CSSProperties } from 'react';
import { AdsenseTrigger } from './adsense-trigger';

export interface AdSenseProps {
  /** AdSense client ID (e.g. `ca-pub-xxxxxxxxxxxxxxxx`) */
  client: string;
  /** AdSense ad slot ID */
  slot: string;
  /** Inline style for the `<ins>` element */
  style?: CSSProperties;
  /** Ad format (e.g. `auto`, `fluid`, `autorelaxed`) */
  format?: string;
  /** Ad layout (e.g. `in-article`) */
  layout?: string;
  /** Enables `data-full-width-responsive="true"` for responsive ads */
  fullWidthResponsive?: boolean;
}

/**
 * AdSense React component for rendering Google AdSense ad units.
 *
 * @remarks
 * This component renders an `<ins class="adsbygoogle">` element and attempts
 * to register it with the global `adsbygoogle` array if present.
 *
 * @example
 * <AdSense client="ca-pub-1165447249910969" slot="3325057139" />
 */
function AdSense({
  client,
  slot,
  style = { display: 'block' },
  format = 'auto',
  layout,
  fullWidthResponsive
}: AdSenseProps): JSX.Element {
  const adRef = useRef<HTMLModElement | null>(null);

  useEffect(() => {
    AdsenseTrigger().catch(console.error);
  }, []);

  // build element props in a single constant to make it easier to extend
  const insProps: Record<string, any> = {
    className: 'adsbygoogle',
    style,
    'data-ad-client': client,
    'data-ad-slot': slot,
    'data-ad-format': format
  };

  if (layout) insProps['data-ad-layout'] = layout;
  if (fullWidthResponsive) insProps['data-full-width-responsive'] = 'true';

  // enable AdSense test mode when running in development-like environments
  if (/dev/i.test(String(process.env.NODE_ENV)) || import.meta.env.DEV) {
    insProps['data-adtest'] = 'on';
  }

  return (
    <div className="w-full min-h-[50px] min-w-[300px] max-w-full overflow-hidden">
      <ins {...(insProps as any)} ref={adRef} />
    </div>
  );
}

export default AdSense;
