import React from 'react';
import { fetchUserInfo, UserInfo } from '../utils/user';
import Link from './Link';
import ThemeSwitcher from './ThemeSwitcher';

interface NavbarState extends UserInfo {
  socialOpen?: boolean;
  userMenuOpen?: boolean;
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
      username: undefined,
      userMenuOpen: false
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
            username: data.username,
            admin: data.admin || false
          });
        } else {
          this.setState({ authenticated: false });
        }
      })
      .catch((error) => {
        console.error('Error fetching user info:', error);
        this.setState({ authenticated: false });
      });
    document.addEventListener('mousedown', this.handleClickOutside);
  }

  componentWillUnmount() {
    document.removeEventListener('mousedown', this.handleClickOutside);
  }

  userMenuRef = React.createRef<HTMLDivElement>();
  socialMenuRef = React.createRef<HTMLDivElement>();

  handleClickOutside = (event: MouseEvent) => {
    const userMenu = this.userMenuRef.current;
    const socialMenu = this.socialMenuRef.current;
    if (
      (this.state.userMenuOpen && userMenu && !userMenu.contains(event.target as Node)) ||
      (this.state.socialOpen && socialMenu && !socialMenu.contains(event.target as Node))
    ) {
      this.setState({ userMenuOpen: false, socialOpen: false });
    }
  };

  handleUserMenuToggle = () => {
    this.setState((prevState) => ({
      userMenuOpen: !prevState.userMenuOpen,
      socialOpen: false
    }));
  };

  handleSocialMenuToggle = () => {
    this.setState((prevState) => ({
      socialOpen: !prevState.socialOpen,
      userMenuOpen: false
    }));
  };

  render() {
    // Dropdown state for social links
    // In class component, use a state property
    const socialOpen = this.state.socialOpen || false;
    const userMenuOpen = this.state.userMenuOpen || false;
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
            {/* User Dropdown for both authenticated and unauthenticated */}
            <div className="relative" ref={this.userMenuRef}>
              <button
                className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium focus:outline-none flex items-center gap-1"
                aria-label="User Menu"
                title="User Menu"
                onClick={this.handleUserMenuToggle}
                type="button">
                <i className="fal fa-user-cog text-2xl" aria-hidden="true"></i>
                <span className="sr-only">User Menu</span>
                <i className={`fal fa-chevron-${userMenuOpen ? 'up' : 'down'} ml-1 text-xs`} aria-hidden="true"></i>
              </button>
              {userMenuOpen && (
                <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg z-50">
                  {this.state.authenticated ? (
                    <>
                      <Link
                        href="/dashboard"
                        className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Dashboard">
                        <i className="fal fa-tachometer-alt mr-2"></i> Dashboard
                      </Link>
                      <Link
                        href="/settings"
                        className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Settings">
                        <i className="fal fa-cog mr-2"></i> Settings
                      </Link>
                      {this.state.admin && (
                        <Link
                          href="/admin"
                          className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                          title="Admin">
                          <i className="fal fa-user-shield mr-2"></i> Admin
                        </Link>
                      )}
                      <Link
                        href="/logout"
                        className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Logout">
                        <i className="fal fa-sign-out-alt mr-2"></i> Logout
                      </Link>
                    </>
                  ) : (
                    <Link
                      href="/login"
                      className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                      title="Login">
                      <i className="fas fa-sign-in-alt mr-2"></i> Login
                    </Link>
                  )}
                </div>
              )}
            </div>
            {/* Social Dropdown */}
            <div className="relative" ref={this.socialMenuRef}>
              <button
                className="text-gray-700 dark:text-gray-200 hover:text-blue-600 dark:hover:text-blue-400 font-medium focus:outline-none flex items-center gap-1"
                aria-label="Social Media"
                title="Social Media"
                onClick={this.handleSocialMenuToggle}
                type="button">
                <i className="fal fa-ellipsis-h text-2xl" aria-hidden="true"></i>
                <span className="sr-only">Social</span>
                <i className={`fal fa-chevron-${socialOpen ? 'up' : 'down'} ml-1 text-xs`} aria-hidden="true"></i>
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
                  <hr className="my-1 border-gray-200 dark:border-gray-700" />
                  <Link
                    href="/about"
                    className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="About">
                    <i className="fal fa-info-circle mr-2"></i> About
                  </Link>
                  <Link
                    href="/changelog"
                    className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Changelog">
                    <i className="fal fa-history mr-2"></i> Changelog
                  </Link>
                  <Link
                    href="/contact"
                    className="flex items-center px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Contact">
                    <i className="fal fa-envelope mr-2"></i> Contact
                  </Link>
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
