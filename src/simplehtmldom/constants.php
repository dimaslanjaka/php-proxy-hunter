<?php

namespace simplehtmldom;

/*
 * Website: http://sourceforge.net/projects/simplehtmldom/
 * Acknowledge: Jose Solorzano (https://sourceforge.net/projects/php-html/)
 *
 * Licensed under The MIT License
 * See the LICENSE file in the project root for more information.
 *
 * Authors:
 *   S.C. Chen
 *   John Schlick
 *   Rus Carroll
 *   logmanoriginal
 *
 * Contributors:
 *   Yousuke Kumakura
 *   Vadim Voituk
 *   Antcs
 *
 * Version Rev. 2.0-RC2 (415)
 */

if (!defined('\simplehtmldom\DEFAULT_TARGET_CHARSET')) {
  define('\simplehtmldom\DEFAULT_TARGET_CHARSET', 'UTF-8');
}
if (!defined('\simplehtmldom\DEFAULT_BR_TEXT')) {
  define('\simplehtmldom\DEFAULT_BR_TEXT', "\r\n");
}
if (!defined('\simplehtmldom\DEFAULT_SPAN_TEXT')) {
  define('\simplehtmldom\DEFAULT_SPAN_TEXT', ' ');
}
if (!defined('\simplehtmldom\MAX_FILE_SIZE')) {
  define('\simplehtmldom\MAX_FILE_SIZE', 2621440);
}
if (!defined('\simplehtmldom\HDOM_SMARTY_AS_TEXT')) {
  define('\simplehtmldom\HDOM_SMARTY_AS_TEXT', 1);
}
