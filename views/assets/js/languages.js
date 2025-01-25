import $ from 'jquery';

/**
 * Updates the language in the URL by setting the 'hl' query parameter
 * based on the selected language from the dropdown.
 */
export function updateLanguage() {
  const selectedLanguage = $('#language-select').val();
  const currentURL = new URL(window.location.href);

  if (selectedLanguage && selectedLanguage.length > 0) {
    // Append or update the 'hl' query parameter
    currentURL.searchParams.set('hl', selectedLanguage);

    // Redirect to the new URL with the updated language
    window.location.href = currentURL.toString();
  }
}

/**
 * Initializes the language selector dropdown based on the current URL's
 * 'hl' query parameter.
 * If the 'hl' parameter is missing, it defaults to 'en'.
 */
export function initializeLanguageSelector() {
  const currentURL = new URL(window.location.href);
  const language = currentURL.searchParams.get('hl') || 'en'; // Default to 'en' if 'hl' is not in the URL

  // Set the value of the select dropdown to the language from the 'hl' parameter
  $('#language-select').val(language);
}
