import { streamJsonFromUrl } from '../../../src/react/utils/json';

describe('streamJsonFromUrl (integration)', () => {
  // Example: Use a real large JSON streaming endpoint
  // This endpoint returns a large array of JSON objects (USGS earthquake data)
  // See: https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_month.geojson
  // The node pattern '!features.*' will yield each earthquake feature object
  it('streams real JSON objects from a public endpoint', async () => {
    const url = 'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_month.geojson';
    const results: any[] = [];
    let count = 0;
    for await (const obj of streamJsonFromUrl(url, '!features.*')) {
      results.push(obj);
      count++;
      if (count >= 5) break; // Only test first 5 objects for speed
    }
    expect(results.length).toBeGreaterThanOrEqual(1);
    expect(results[0]).toHaveProperty('type');
    expect(results[0]).toHaveProperty('geometry');
    expect(results[0]).toHaveProperty('properties');
  });
});
