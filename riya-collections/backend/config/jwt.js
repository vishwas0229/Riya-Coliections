const jwt = require('jsonwebtoken');
const crypto = require('crypto');
require('dotenv').config();

const jwtConfig = {
  secret: process.env.JWT_SECRET || 'fallback_secret_key',
  expiresIn: process.env.JWT_EXPIRES_IN || '24h',
  refreshSecret: process.env.JWT_REFRESH_SECRET || 'fallback_refresh_secret',
  refreshExpiresIn: process.env.JWT_REFRESH_EXPIRES_IN || '7d'
};

// Generate access token
function generateAccessToken(payload) {
  return jwt.sign(payload, jwtConfig.secret, {
    expiresIn: jwtConfig.expiresIn,
    issuer: 'riya-collections',
    audience: 'riya-collections-users'
  });
}

// Generate refresh token
function generateRefreshToken(payload) {
  return jwt.sign(payload, jwtConfig.refreshSecret, {
    expiresIn: jwtConfig.refreshExpiresIn,
    issuer: 'riya-collections',
    audience: 'riya-collections-users'
  });
}

// Verify access token
function verifyAccessToken(token) {
  try {
    return jwt.verify(token, jwtConfig.secret, {
      issuer: 'riya-collections',
      audience: 'riya-collections-users'
    });
  } catch (error) {
    throw new Error('Invalid or expired access token');
  }
}

// Verify refresh token
function verifyRefreshToken(token) {
  try {
    return jwt.verify(token, jwtConfig.refreshSecret, {
      issuer: 'riya-collections',
      audience: 'riya-collections-users'
    });
  } catch (error) {
    throw new Error('Invalid or expired refresh token');
  }
}

// Generate token pair
function generateTokenPair(payload) {
  // Add a small unpredictable claim to ensure refreshed tokens differ
  const nonce = crypto.randomBytes(8).toString('hex');
  const payloadWithNonce = Object.assign({}, payload, { jti: nonce });

  const accessToken = generateAccessToken(payloadWithNonce);
  const refreshToken = generateRefreshToken(payloadWithNonce);
  
  return {
    accessToken,
    refreshToken,
    expiresIn: jwtConfig.expiresIn
  };
}

module.exports = {
  jwtConfig,
  generateAccessToken,
  generateRefreshToken,
  verifyAccessToken,
  verifyRefreshToken,
  generateTokenPair
};