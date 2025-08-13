import React from 'react';
import { fetchUserInfo, UserInfo } from '../utils/user';
import Link from './Link';
import ThemeSwitcher from './ThemeSwitcher';

interface NavbarState extends UserInfo {
  socialOpen?: boolean;
}

class Navbar extends React.Component<object, NavbarState> {
  constructor(props: object) {
    super(props);
    this.state = {
      authenticated: false,
      email: undefined,
      first_name: undefined,
      last_name: undefined,
      saldo: undefined,
      uid: undefined,
      username: undefined
    };
  }

  componentDidMount() {
    fetchUserInfo()
      .then((data: UserInfo) => {
        if (data.authenticated) {
          this.setState({
            authenticated: true,
            email: data.email,
            first_name: data.first_name,
            last_name: data.last_name,
            saldo: data.saldo,
            uid: data.uid,
            username: data.username
          });
        } else {
          this.setState({ authenticated: false });
        }
      })
      .catch((error) => {
        console.error('Error fetching user info:', error);
        this.setState({ authenticated: false });
      });
  }

  render() {
    // Dropdown state for social links
    // In class component, use a state property
    const socialOpen = this.state.socialOpen || false;
    return (
      <nav className="w-full bg-white dark:bg-gray-900 shadow-md">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
          <div className="flex items-center">
            <Link href="/" className="text-2xl font-bold text-blue-600 dark:text-white" title="Home">
              DX
            </Link>
          </div>
          <div className="flex items-center space-x-4">
            <Link
              href="/proxyManager.html"
              className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
              aria-label="Proxy Manager"
              title="Proxy Manager">
              <i className="fal fa-network-wired text-2xl" aria-hidden="true"></i>
              <span className="sr-only">Proxy</span>
            </Link>
            {this.state.authenticated && (
              <Link
                href="/dashboard"
                className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
                aria-label="Dashboard"
                title="Dashboard">
                <i className="fal fa-tachometer-alt text-2xl" aria-hidden="true"></i>
                <span className="sr-only">Dashboard</span>
              </Link>
            )}
            {this.state.authenticated && (
              <Link
                href="/settings"
                className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
                aria-label="Settings"
                title="Settings">
                <i className="fal fa-cog text-2xl" aria-hidden="true"></i>
                <span className="sr-only">Settings</span>
              </Link>
            )}
            {!this.state.authenticated && (
              <Link
                href="/login"
                className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium"
                aria-label="Login"
                title="Login">
                <i className="fas fa-sign-in-alt text-2xl" aria-hidden="true"></i>
                <span className="sr-only">Login</span>
              </Link>
            )}
            {/* Social Dropdown */}
            <div className="relative">
              <button
                className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium focus:outline-none"
                aria-label="Social Media"
                title="Social Media"
                onClick={() => this.setState({ socialOpen: !socialOpen })}
                type="button">
                <i className="fal fa-ellipsis-h text-2xl" aria-hidden="true"></i>
                <span className="sr-only">Social</span>
              </button>
              {socialOpen && (
                <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg z-50">
                  <a
                    href="https://github.com/dimaslanjaka/php-proxy-hunter"
                    className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="GitHub Repository">
                    <i className="fab fa-github mr-2"></i> GitHub
                  </a>
                  <a
                    href="https://twitter.com/dimaslanjaka"
                    className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Twitter">
                    <i className="fab fa-twitter mr-2"></i> Twitter
                  </a>
                  {/* Add more social links here */}
                </div>
              )}
            </div>
            <ThemeSwitcher />
          </div>
        </div>
      </nav>
    );
  }
}

export default Navbar;
