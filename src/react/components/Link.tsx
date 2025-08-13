import React from 'react';
import { Link as OriginalLink } from 'react-router-dom';
import safelink from 'safelinkify';
import { isValidHttpUrl } from '../utils/url.js';

export const sfInstance = new safelink.safelink({
  exclude: [/\.webmanajemen\.com/],
  redirect: '/outbound?url=',
  password: 'php-proxy-hunter',
  verbose: true
});

export interface LinkProps {
  /** Target URL */
  href?: string;
  /** Same as href */
  to?: string;
  /** Open in target tab */
  target?: string;
  /** Class names */
  className?: string;
  /** Link title */
  title?: string;
  /** Rel attribute */
  rel?: string;
  /** Link content */
  children?: React.ReactNode;
  /** React key */
  key?: React.Key;
  /** Any other props */
  [otherProps: string]: any;
}

/**
 * React Safelink Converter
 * Anonymize external links into page redirector
 */
class Link extends React.Component<LinkProps, { sf: any }> {
  _isMounted: boolean;
  constructor(props: LinkProps) {
    super(props);
    this.state = { sf: null };
    this._isMounted = false;
  }

  componentDidMount() {
    this._isMounted = true;
    if (this._isMounted) {
      this.setState({ sf: sfInstance });
    }
  }

  componentWillUnmount() {
    this._isMounted = false;
  }

  render() {
    const { href, to, ...props } = this.props;
    const dest = String(href || to || '').trim();
    let result = dest;
    let type = 'internal';
    const { sf } = this.state;
    if (typeof dest === 'string' && sf) {
      if (isValidHttpUrl(dest)) {
        result = sf.parseUrl(dest);
        if (result === dest) {
          type = 'internal';
        } else {
          type = 'external';
        }
      }
    }

    let render;

    if (type === 'external') {
      render = (
        <a {...props} href={result} target="_blank" rel="noreferrer">
          {this.props.children}
        </a>
      );
    } else {
      render = (
        <OriginalLink {...props} to={dest}>
          {this.props.children}
        </OriginalLink>
      );
    }

    return render;
  }
}

export default Link;
