declare global {
  interface Window {
    adsbygoogle?: any[];
    adsense_option?: {
      localhost?: string[];
    };
    // track which ad slots we've already pushed/handled to avoid duplicate pushes
    __adsenseHandledSlots?: Set<string>;
    // adsenseInitialized?: boolean;
  }
}

/**
 * initialize background image, height, ad-test to existing `ins` tags
 */
export default function applyEnviromentAds() {
  // ensure we have a global Set to track handled slots
  if (!window.__adsenseHandledSlots) window.__adsenseHandledSlots = new Set();
  // apply ad testing
  const nodes = document.querySelectorAll('ins.adsbygoogle');
  const existingIns: HTMLElement[] = Array.from(nodes) as HTMLElement[];
  console.info('existing ins', existingIns.length);

  // Loop with for..of for better readability and fewer index ops
  for (const insEl of existingIns) {
    const ins = insEl as HTMLElement;
    if (!ins) continue;

    // Only consider static ins units created in markup (skip ones marked dynamic)
    const { adClient, adSlot } = ins.dataset;
    const isDynamic = ins.hasAttribute('dynamic');

    // If this ins already reports that an ad has been loaded, skip it
    const insStatusDone = ins.getAttribute && ins.getAttribute('data-adsbygoogle-status') === 'done';
    const slotKey = ins.dataset.adSlot || ins.getAttribute('data-ad-slot') || '';
    if (insStatusDone) {
      console.info('skip ins, already done', slotKey);
      // mark in global set as well
      try {
        if (slotKey) window.__adsenseHandledSlots!.add(slotKey);
      } catch {}
      continue;
    }
    // skip if we've already handled this slot globally
    if (slotKey && window.__adsenseHandledSlots!.has(slotKey)) {
      console.info('skip ins, slot already handled', slotKey);
      continue;
    }

    if (adClient && adSlot && !isDynamic) {
      // apply background image and min-height to make placeholder visible
      // anonymize client/slot when showing placeholder
      const clientId = adClient.replace('ca-pub-', '');
      const anonClient = clientId.slice(0, 3) + 'xxx' + clientId.slice(Math.max(0, clientId.length - 3));
      const anonSlot = adSlot.slice(0, 3) + 'xxx' + adSlot.slice(Math.max(0, adSlot.length - 3));
      // Use dummyimage.com as a maintained placeholder service and URL-encode text
      const placeholderText = encodeURIComponent(`${anonClient}-${anonSlot}`);
      const bg = `https://dummyimage.com/200x20/ffffff/000000.png&text=${placeholderText}`;

      try {
        // set multiple style properties in one place
        ins.style.backgroundImage = `url('${bg}')`;
        ins.style.backgroundRepeat = 'no-repeat';
        ins.style.minHeight = '50px';
      } catch {}
    }

    // Apply test ad setting in development-like environments
    if (import.meta.env.DEV || isLocalHostname()) {
      console.info('apply test ad to existing ins', ins.dataset.adSlot || ins.getAttribute('data-ad-slot'));
      ins.setAttribute('data-adtest', 'on');
    }

    // trigger ad load when not already loaded
    const slot = ins.dataset.adSlot || ins.getAttribute('data-ad-slot');
    const refreshIns = document.querySelector(`ins.adsbygoogle[data-ad-slot="${slot}"]`) as HTMLElement | null;

    // Robust detection: check for iframe child, Google host div (aswift_*),
    // or status attributes that indicate the ad is loaded or in the process.
    const hasIframe = !!refreshIns?.querySelector('iframe');
    const hasAswiftHost = !!refreshIns?.querySelector('[id^="aswift_"]');
    const hasStatusDone = refreshIns?.getAttribute('data-adsbygoogle-status') === 'done';
    const hasLoadComplete = !!refreshIns?.querySelector('[data-load-complete="true"]');

    // Debug: helpful during development
    console.debug('adsense check', { slot, hasIframe, hasAswiftHost, hasStatusDone, hasLoadComplete, el: refreshIns });

    // If any indicator is present, assume ad already exists and skip pushing.
    if (hasIframe || hasAswiftHost || hasStatusDone || hasLoadComplete) {
      console.info('adsense check (skip push)', { slot, hasIframe, hasAswiftHost, hasStatusDone, hasLoadComplete });
      // mark handled
      if (slot) window.__adsenseHandledSlots!.add(slot);
      continue;
    }

    if (slot && refreshIns) {
      console.info('trigger ad load for slot', slot, refreshIns);
      // mark handled before pushing to avoid races causing duplicate pushes
      window.__adsenseHandledSlots!.add(slot);
      window.adsbygoogle?.push({ params: { google_ad_slot: slot } });
    }
  }
}

function isLocalHostname() {
  const { localhost: localdomains = [] } = window.adsense_option || {};
  return localdomains.includes(window.location.hostname);
}

export async function AdsenseTrigger() {
  // Ensure global adsbygoogle array exists so pushes won't fail
  if (!window.adsbygoogle) window.adsbygoogle = [] as any[];

  // run once
  // if (window.adsenseInitialized) return;
  // window.adsenseInitialized = true;

  applyEnviromentAds();
}
