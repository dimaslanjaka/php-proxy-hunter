import oboe from 'oboe';

/**
 * Streams JSON from a URL using oboe.js and yields parsed objects as they arrive.
 * @param url The endpoint to stream JSON from.
 * @param nodePattern The oboe node pattern to match (e.g., '!.*' for root array items).
 * @returns Async generator yielding parsed JSON objects.
 */
export async function* streamJsonFromUrl<T = any>(
  url: string,
  nodePattern: string = '!.*'
): AsyncGenerator<T, void, unknown> {
  let resolve: ((value: T | undefined) => void) | null = null;
  let reject: ((reason?: any) => void) | null = null;
  let done = false;
  let error: any = null;
  const queue: T[] = [];

  const nextValue = () => {
    if (queue.length && resolve) {
      resolve(queue.shift());
      resolve = null;
    }
    if (done && resolve) {
      resolve(undefined);
      resolve = null;
    }
    if (error && reject) {
      reject(error);
      reject = null;
    }
  };

  // Use oboe options to set no-cache headers in browser
  const ob = oboe({
    url,
    method: 'GET',
    headers: {
      'Cache-Control': 'no-cache',
      Pragma: 'no-cache'
    }
  }).node(nodePattern, (node: T) => {
    queue.push(node);
    nextValue();
    return oboe.drop; // Don't keep in memory
  });

  // Defensive chaining: .done may not exist in mock, so check before chaining .fail
  if (typeof ob.done === 'function') {
    const doneResult = ob.done(() => {
      done = true;
      nextValue();
    });
    if (doneResult && typeof doneResult.fail === 'function') {
      doneResult.fail((err: any) => {
        error = err;
        nextValue();
      });
    } else if (typeof ob.fail === 'function') {
      ob.fail((err: any) => {
        error = err;
        nextValue();
      });
    }
  } else if (typeof ob.fail === 'function') {
    ob.fail((err: any) => {
      error = err;
      nextValue();
    });
  }

  while (true) {
    if (queue.length) {
      const value = queue.shift();
      if (value !== undefined) {
        yield value;
        continue;
      }
    }
    if (done) break;
    if (error) throw error;
    await new Promise<T | undefined>((res, rej) => {
      resolve = res;
      reject = rej;
    });
  }
}
