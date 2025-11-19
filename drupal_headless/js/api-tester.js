/**
 * @file
 * API Tester functionality for Drupal Headless Module.
 */

(function () {
  'use strict';

  window.drupalHeadlessApiTester = {
    /**
     * Tests OAuth2 token endpoint.
     */
    testToken: function () {
      const clientId = document.getElementById('api-tester-consumer').value;
      const clientSecret = document.getElementById('api-tester-secret').value;
      const resultsContainer = document.getElementById('api-tester-results');

      if (!clientSecret) {
        this.showError('Please enter a client secret.');
        return;
      }

      this.showLoading('Testing OAuth2 token endpoint...');

      const tokenUrl = window.location.origin + '/oauth/token';

      const formData = new URLSearchParams();
      formData.append('grant_type', 'client_credentials');
      formData.append('client_id', clientId);
      formData.append('client_secret', clientSecret);

      fetch(tokenUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: formData.toString(),
      })
        .then(response => response.json())
        .then(data => {
          if (data.access_token) {
            this.showSuccess('OAuth2 Token Test', {
              'Status': '✓ Success',
              'Token Type': data.token_type,
              'Expires In': data.expires_in + ' seconds',
              'Access Token': data.access_token.substring(0, 50) + '...',
            });

            // Store token for subsequent tests.
            sessionStorage.setItem('api_test_token', data.access_token);
          } else {
            this.showError('Failed to obtain access token', data);
          }
        })
        .catch(error => {
          this.showError('OAuth2 token request failed', { error: error.message });
        });
    },

    /**
     * Tests JSON:API access.
     */
    testJsonApi: function () {
      const token = sessionStorage.getItem('api_test_token');

      if (!token) {
        this.showError('Please test OAuth2 token first to obtain an access token.');
        return;
      }

      this.showLoading('Testing JSON:API access...');

      const jsonApiUrl = window.location.origin + '/jsonapi';

      fetch(jsonApiUrl, {
        method: 'GET',
        headers: {
          'Authorization': 'Bearer ' + token,
          'Accept': 'application/vnd.api+json',
        },
      })
        .then(response => {
          if (!response.ok) {
            throw new Error('HTTP ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          const resources = data.links ? Object.keys(data.links).length : 0;

          this.showSuccess('JSON:API Access Test', {
            'Status': '✓ Success',
            'Available Resources': resources,
            'API Version': data.jsonapi?.version || 'N/A',
            'Endpoint': jsonApiUrl,
          });
        })
        .catch(error => {
          this.showError('JSON:API access failed', { error: error.message });
        });
    },

    /**
     * Tests CORS configuration.
     */
    testCors: function () {
      this.showLoading('Testing CORS configuration...');

      const testUrl = window.location.origin + '/admin/drupal-headless/api-test/endpoint?type=cors';

      fetch(testUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
      })
        .then(response => response.json())
        .then(data => {
          const headers = {};

          // Check for CORS headers (note: we can't actually read them from same-origin request).
          this.showSuccess('CORS Configuration', {
            'CORS Enabled': data.cors.enabled ? '✓ Yes' : '✗ No',
            'Allowed Origins': Array.isArray(data.cors.allowed_origins)
              ? data.cors.allowed_origins.join(', ')
              : data.cors.allowed_origins || 'None configured',
            'Note': 'To fully test CORS, make a request from your frontend application at a different origin.',
          });
        })
        .catch(error => {
          this.showError('CORS test failed', { error: error.message });
        });
    },

    /**
     * Shows a loading message.
     */
    showLoading: function (message) {
      const resultsContainer = document.getElementById('api-tester-results');
      resultsContainer.innerHTML = '<div class="api-tester-loading">' +
        '<div class="api-tester-spinner"></div>' +
        '<p>' + message + '</p>' +
        '</div>';
    },

    /**
     * Shows a success message with results.
     */
    showSuccess: function (title, results) {
      const resultsContainer = document.getElementById('api-tester-results');

      let html = '<div class="messages messages--status api-tester-result">';
      html += '<h3>' + title + '</h3>';
      html += '<table class="api-tester-results-table">';

      for (const [key, value] of Object.entries(results)) {
        html += '<tr>';
        html += '<td class="api-tester-label"><strong>' + key + ':</strong></td>';
        html += '<td class="api-tester-value">' + this.escapeHtml(value) + '</td>';
        html += '</tr>';
      }

      html += '</table></div>';

      resultsContainer.innerHTML = html;
    },

    /**
     * Shows an error message.
     */
    showError: function (message, details) {
      const resultsContainer = document.getElementById('api-tester-results');

      let html = '<div class="messages messages--error api-tester-result">';
      html += '<h3>✗ ' + message + '</h3>';

      if (details) {
        html += '<pre style="background: #fff; padding: 10px; margin-top: 10px; overflow-x: auto;">';
        html += JSON.stringify(details, null, 2);
        html += '</pre>';
      }

      html += '</div>';

      resultsContainer.innerHTML = html;
    },

    /**
     * Escapes HTML to prevent XSS.
     */
    escapeHtml: function (text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },
  };
})();
