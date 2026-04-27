import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api, { setUnauthorizedHandler } from '../services/client';

const AuthContext = createContext(null);
const DEVICE_ID_KEY = 'extracash_device_id_v1';

async function getOrCreateDeviceId() {
  const existing = await AsyncStorage.getItem(DEVICE_ID_KEY);
  if (existing && `${existing}`.trim() !== '') return existing;
  const fresh = `dev_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
  await AsyncStorage.setItem(DEVICE_ID_KEY, fresh);
  return fresh;
}

export function AuthProvider({ children }) {
  const [user, setUser]   = useState(null);
  const [wallet, setWallet] = useState(null);
  const [loading, setLoading] = useState(true);

  // Restore session on mount
  useEffect(() => {
    (async () => {
      const token = await AsyncStorage.getItem('token');
      if (token) {
        try {
          const res = await api.get('/me');
          setUser(res.data.user);
          setWallet(res.data.wallet);
        } catch {
          await AsyncStorage.removeItem('token');
        }
      }
      setLoading(false);
    })();
  }, []);

  useEffect(() => {
    setUnauthorizedHandler(async () => {
      setUser(null);
      setWallet(null);
    });

    return () => setUnauthorizedHandler(null);
  }, []);

  const login = async (email, password) => {
    const deviceId = await getOrCreateDeviceId();
    const res = await api.post('/login', { email, password, device_id: deviceId });
    await AsyncStorage.setItem('token', res.data.token);
    setUser(res.data.user);
    setWallet(res.data.wallet || res.data.user?.wallet || null);
    return res.data;
  };

  const register = async (payload, config = {}) => {
    const res = await api.post('/register', payload, config);
    // Registration now returns otp_required; token is issued after phone OTP verification.
    return res.data;
  };

  const verifyRegisterOtp = async ({ email, otp_id, otp_code }) => {
    const res = await api.post('/register/otp/verify', { email, otp_id, otp_code });
    await AsyncStorage.setItem('token', res.data.token);
    setUser(res.data.user);
    setWallet(res.data.wallet || res.data.user?.wallet || null);
    return res.data;
  };

  const verifyLoginOtp = async ({ email, otp_id, otp_code }) => {
    const deviceId = await getOrCreateDeviceId();
    const res = await api.post('/login/otp/verify', { email, otp_id, otp_code, device_id: deviceId });
    await AsyncStorage.setItem('token', res.data.token);
    setUser(res.data.user);
    setWallet(res.data.wallet || res.data.user?.wallet || null);
    return res.data;
  };

  const requestPasswordResetOtp = async (identifier) => {
    const res = await api.post('/password/otp/request', { identifier });
    return res.data;
  };

  const verifyPasswordResetOtp = async ({ email, otp_id, otp_code }) => {
    const res = await api.post('/password/otp/verify', { email, otp_id, otp_code });
    return res.data;
  };

  const resetPassword = async ({ reset_session, password }) => {
    const res = await api.post('/password/reset', { reset_session, password });
    await AsyncStorage.setItem('token', res.data.token);
    setUser(res.data.user);
    setWallet(res.data.wallet || res.data.user?.wallet || null);
    return res.data;
  };

  const logout = async () => {
    try { await api.post('/logout'); } catch {}
    await AsyncStorage.removeItem('token');
    setUser(null);
    setWallet(null);
  };

  const refreshWallet = async () => {
    const res = await api.get('/me');
    setUser(res.data.user);
    setWallet(res.data.wallet);
  };

  return (
    <AuthContext.Provider value={{ user, wallet, loading, login, register, verifyRegisterOtp, verifyLoginOtp, requestPasswordResetOtp, verifyPasswordResetOtp, resetPassword, logout, refreshWallet }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => useContext(AuthContext);
