/**
 * Simple WebAuthn helper for base64url conversions
 */
export const bufferToBase64url = (buffer) => {
  const byteView = new Uint8Array(buffer);
  let str = '';
  for (const charCode of byteView) {
    str += String.fromCharCode(charCode);
  }
  return btoa(str)
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '');
};

export const base64urlToBuffer = (base64url) => {
  const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
  const padLen = (4 - (base64.length % 4)) % 4;
  const str = atob(base64 + '='.repeat(padLen));
  const buffer = new ArrayBuffer(str.length);
  const byteView = new Uint8Array(buffer);
  for (let i = 0; i < str.length; i++) {
    byteView[i] = str.charCodeAt(i);
  }
  return buffer;
};

export const publicKeyCredentialToJSON = (pubKeyCred) => {
  if (pubKeyCred instanceof Array) {
    return pubKeyCred.map(publicKeyCredentialToJSON);
  }

  if (pubKeyCred instanceof ArrayBuffer) {
    return bufferToBase64url(pubKeyCred);
  }

  if (pubKeyCred instanceof Object) {
    const obj = {};
    for (const key in pubKeyCred) {
      obj[key] = publicKeyCredentialToJSON(pubKeyCred[key]);
    }
    return obj;
  }

  return pubKeyCred;
};

// Recursive function to convert base64url strings back to ArrayBuffers in the options object
export const parseOptions = (options) => {
  const bufferKeys = ['challenge', 'user.id', 'allowCredentials.id', 'id'];
  
  const process = (obj, path = '') => {
    for (const key in obj) {
      const currentPath = path ? `${path}.${key}` : key;
      if (typeof obj[key] === 'string' && (key === 'challenge' || key === 'id' || currentPath.endsWith('.id'))) {
         try {
            obj[key] = base64urlToBuffer(obj[key]);
         } catch (e) {}
      } else if (typeof obj[key] === 'object' && obj[key] !== null) {
        process(obj[key], currentPath);
      }
    }
  };
  
  // Clone to avoid mutation
  const cloned = JSON.parse(JSON.stringify(options));
  
  // Specific fixes for WebAuthn options
  if (cloned.challenge) cloned.challenge = base64urlToBuffer(cloned.challenge);
  if (cloned.user && cloned.user.id) cloned.user.id = base64urlToBuffer(cloned.user.id);
  if (cloned.allowCredentials) {
    cloned.allowCredentials = cloned.allowCredentials.map(cred => ({
      ...cred,
      id: base64urlToBuffer(cred.id)
    }));
  }
  
  return cloned;
};
