/**
 * Dynamic Expo config: allow Android cleartext only when the API base URL is http://
 * (typical local dev). Production / staging with https:// leaves cleartext disabled.
 */
const appJson = require('./app.json');

const apiUrl = process.env.EXPO_PUBLIC_API_URL || '';
const allowCleartext =
  typeof apiUrl === 'string' && apiUrl.trim().toLowerCase().startsWith('http://');

module.exports = {
  expo: {
    ...appJson.expo,
    android: {
      ...appJson.expo.android,
      usesCleartextTraffic: allowCleartext,
    },
  },
};
