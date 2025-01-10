/**
 * Splits an array into chunks, either by a specified chunk size or into a specified number of chunks.
 *
 * @template T
 * @param {T[]} array - The array to split into chunks.
 * @param {number} [chunkSize] - The size of each chunk. If provided, the array is split into chunks of this size.
 * @param {number} [totalChunks] - The number of chunks to split the array into. If provided, the array is split into this many chunks.
 * @returns {T[][]} - A new array where each element is a chunk of the original array.
 * @throws {Error} - If neither `chunkSize` nor `totalChunks` is provided.
 */
export function splitArrayIntoChunks(array, chunkSize = null, totalChunks = null) {
  if (chunkSize !== null) {
    // Split by specific chunk size
    return array.reduce((chunks, _, i) => {
      if (i % chunkSize === 0) chunks.push(array.slice(i, i + chunkSize));
      return chunks;
    }, []);
  } else if (totalChunks !== null) {
    // Split into a specific number of chunks
    const chunkSize = Math.floor(array.length / totalChunks);
    const remainder = array.length % totalChunks;
    const chunks = [];
    let start = 0;

    for (let i = 0; i < totalChunks; i++) {
      const end = start + chunkSize + (i < remainder ? 1 : 0);
      chunks.push(array.slice(start, end));
      start = end;
    }

    return chunks;
  } else {
    throw new Error('Either chunkSize or totalChunks must be provided.');
  }
}

/**
 * Shuffles an array in place using the Fisher-Yates algorithm.
 *
 * @template T
 * @param {T[]} array - The array to shuffle.
 * @returns {T[]} The shuffled array.
 */
export function array_shuffle(array) {
  for (let i = array.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [array[i], array[j]] = [array[j], array[i]];
  }
  return array;
}
