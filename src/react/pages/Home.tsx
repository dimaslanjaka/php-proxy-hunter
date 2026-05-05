import Link from '../components/Link';
import routes from '../routes.json';
import { useEffect, useRef, useState } from 'react';

const Home = () => {
  const allRoutes = routes as any[];
  const pageSize = 12;
  const [visibleCount, setVisibleCount] = useState(Math.min(pageSize, allRoutes.length));
  const containerRef = useRef<HTMLDivElement | null>(null);
  const tickingRef = useRef(false);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;

    const onScroll = () => {
      if (tickingRef.current) return;
      tickingRef.current = true;
      requestAnimationFrame(() => {
        if (!containerRef.current) {
          tickingRef.current = false;
          return;
        }
        const { scrollTop, clientHeight, scrollHeight } = containerRef.current;
        const nearBottom = scrollTop + clientHeight >= scrollHeight - 200;
        if (nearBottom && visibleCount < allRoutes.length) {
          setVisibleCount((v) => Math.min(allRoutes.length, v + pageSize));
        }
        tickingRef.current = false;
      });
    };

    el.addEventListener('scroll', onScroll, { passive: true });
    return () => el.removeEventListener('scroll', onScroll);
  }, [visibleCount, allRoutes.length]);

  return (
    <>
      <div className="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
        {/* Hero Section */}
        <section className="relative bg-white dark:bg-gray-900">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="text-center py-20">
              <h1 className="text-4xl font-extrabold tracking-tight sm:text-5xl md:text-6xl text-gray-900 dark:text-gray-100">
                Build Your Future
              </h1>
              <p className="mt-6 text-lg sm:mt-8 sm:text-xl sm:max-w-2xl sm:mx-auto text-gray-700 dark:text-gray-300">
                Discover the tools and insights to take your business to the next level. Join thousands of innovators
                today!
              </p>
              <div className="mt-8 flex flex-col sm:flex-row justify-center gap-4">
                <Link
                  href="/proxy-list"
                  className="px-8 py-3 border rounded-md text-lg font-medium hover:bg-opacity-75 flex items-center gap-2
                  bg-blue-600 text-white border-blue-700
                  dark:bg-blue-700 dark:text-white dark:border-blue-600">
                  <i className="fas fa-server"></i>
                  Proxy Manager
                </Link>
                <Link
                  href="/changelog"
                  className="px-8 py-3 border rounded-md text-lg font-medium hover:bg-opacity-75 flex items-center gap-2
                  bg-green-100 text-green-900 border-green-300
                  dark:bg-green-800 dark:text-green-100 dark:border-green-700">
                  <i className="fa-duotone fa-clock-rotate-left"></i>
                  Changelog
                </Link>
              </div>
            </div>
          </div>
        </section>

        {/* Features Section */}
        <section className="py-16 bg-gray-50 dark:bg-gray-800">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12">
              <div className="text-center">
                <div className="flex justify-center items-center w-16 h-16 border rounded-full mx-auto bg-blue-600 dark:bg-blue-700 text-white">
                  {/* SVG Icon 1 */}
                  <svg
                    className="w-8 h-8"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M3 10h11M9 21V3m12 10H9m3 7h3m4 0h3m-4-4h3m-6 0h3M9 7h3m6 0h3"
                    />
                  </svg>
                </div>
                <h3 className="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Innovative Tools</h3>
                <p className="mt-2 text-gray-700 dark:text-gray-300">
                  Access a range of tools to enhance productivity and achieve your goals faster.
                </p>
              </div>
              <div className="text-center">
                <div className="flex justify-center items-center w-16 h-16 border rounded-full mx-auto bg-blue-600 dark:bg-blue-700 text-white">
                  {/* SVG Icon 2 */}
                  <svg
                    className="w-8 h-8"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M13 16h-1v-4h-1m5 4h-1v-6h-1m-4 6h1m-5-4h1m8-6H9m2-4H5m6 0h6"
                    />
                  </svg>
                </div>
                <h3 className="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Actionable Insights</h3>
                <p className="mt-2 text-gray-700 dark:text-gray-300">
                  Gain insights that drive results and keep you ahead of the competition.
                </p>
              </div>
              <div className="text-center">
                <div className="flex justify-center items-center w-16 h-16 border rounded-full mx-auto bg-blue-600 dark:bg-blue-700 text-white">
                  {/* SVG Icon 3 */}
                  <svg
                    className="w-8 h-8"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M9.75 9.75l-1.5-1.5m0 0L8 8m3-3H5m6 0h6m-6 6h6m-6 6h6m6-6h-3m-6 6h3m-6-9H5m6 0h6"
                    />
                  </svg>
                </div>
                <h3 className="mt-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Scalable Solutions</h3>
                <p className="mt-2 text-gray-700 dark:text-gray-300">
                  Grow your business with flexible and scalable solutions tailored to your needs.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* Posts / Routers Section (infinite scroll, fixed height) */}
        <section className="py-16 bg-white dark:bg-gray-900">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 className="text-2xl font-extrabold text-gray-900 dark:text-gray-100">Latest Posts & Routers</h2>
            <p className="mt-2 text-gray-700 dark:text-gray-300">Scroll to load more posts and routes.</p>

            <div className="mt-8">
              <div
                ref={containerRef}
                className="h-96 md:h-[600px] overflow-auto border rounded-lg p-4 bg-gray-50 dark:bg-gray-800"
                aria-label="Posts list">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                  {allRoutes.slice(0, visibleCount).map((r, idx) => (
                    <div
                      key={(r && (Array.isArray(r.path) ? r.path.join('|') : r.path)) || idx}
                      className="p-4 border rounded bg-white dark:bg-gray-900">
                      {r.thumbnail && (
                        <div className="mb-3">
                          <img
                            src={r.thumbnail}
                            alt={r.title || r.name || `thumbnail-${idx}`}
                            className="w-full h-36 object-cover rounded-md"
                          />
                        </div>
                      )}
                      <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                        <Link
                          href={Array.isArray(r.path) ? r.path[0] : r.path || r.url || '#'}
                          className="hover:underline">
                          {r.title || r.name || (Array.isArray(r.path) ? r.path.join(', ') : r.path) || r.url}
                        </Link>
                      </h3>
                      {r.description && (
                        <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">{r.description}</p>
                      )}
                    </div>
                  ))}
                </div>
              </div>

              <div className="mt-3 text-center text-sm text-gray-600 dark:text-gray-400">
                {visibleCount < allRoutes.length
                  ? `Showing ${visibleCount} of ${allRoutes.length} — scroll to load more`
                  : `Showing all ${allRoutes.length} items`}
              </div>
            </div>
          </div>
        </section>

        {/* CTA Section */}
        <section className="py-16 bg-white dark:bg-gray-900">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 className="text-3xl font-extrabold sm:text-4xl text-gray-900 dark:text-gray-100">
              Ready to Transform Your Future?
            </h2>
            <p className="mt-4 text-lg text-gray-700 dark:text-gray-300">
              Start your journey today with our platform. It’s time to achieve the success you deserve.
            </p>
            <div className="mt-8">
              <Link
                href="#"
                className="px-8 py-3 border rounded-md text-lg font-medium hover:bg-opacity-75 bg-blue-600 dark:bg-blue-700 text-white">
                Get Started Now
              </Link>
            </div>
          </div>
        </section>
      </div>
    </>
  );
};

export default Home;
