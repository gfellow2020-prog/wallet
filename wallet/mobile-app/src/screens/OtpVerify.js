import React, { useMemo, useState } from 'react';
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, StyleSheet, Keyboard, Platform } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';

const APP_YEAR = new Date().getFullYear();

function normalizeOtp(text) {
  return (text || '').replace(/\D/g, '').slice(0, 6);
}

export default function OtpVerify({ navigation, route }) {
  const { verifyRegisterOtp, verifyLoginOtp, verifyPasswordResetOtp } = useAuth();
  const { alert } = useDialog();

  const email = `${route?.params?.email || ''}`.trim().toLowerCase();
  const otpId = route?.params?.otpId != null ? Number(route.params.otpId) : null;
  const purpose = `${route?.params?.purpose || ''}`.trim(); // 'phone_verify' | 'login' | 'password_reset'

  const title = useMemo(() => {
    if (purpose === 'login') return 'Confirm sign in';
    if (purpose === 'password_reset') return 'Reset password';
    return 'Verify your number';
  }, [purpose]);

  const subtitle = useMemo(() => {
    if (purpose === 'login') return 'Enter the OTP we sent to confirm it’s really you.';
    if (purpose === 'password_reset') return 'Enter the OTP we sent so you can create a new password.';
    return 'Enter the OTP we sent to confirm your mobile number.';
  }, [purpose]);

  const [otpCode, setOtpCode] = useState('');
  const [loading, setLoading] = useState(false);

  const submitOtp = async (code) => {
    const clean = normalizeOtp(code ?? otpCode);
    if (!email || !otpId || clean.length !== 6) {
      return alert({ title: 'OTP required', message: 'Enter the 6-digit OTP sent to your phone/email.', tone: 'warn' });
    }

    setLoading(true);
    try {
      if (purpose === 'login') {
        await verifyLoginOtp({
          email,
          otp_id: otpId,
          otp_code: clean,
        });
      } else if (purpose === 'password_reset') {
        const res = await verifyPasswordResetOtp({
          email,
          otp_id: otpId,
          otp_code: clean,
        });
        if (res?.reset_session) {
          navigation.replace('ResetPassword', { resetSession: String(res.reset_session) });
          return;
        }
        await alert({ title: 'Verification failed', message: 'Could not start reset session. Try again.', tone: 'danger' });
      } else {
        await verifyRegisterOtp({
          email,
          otp_id: otpId,
          otp_code: clean,
        });
      }
    } catch (err) {
      const msg = err?.response?.data?.message || 'OTP verification failed';
      await alert({ title: 'Verification failed', message: msg, tone: 'danger' });
    } finally {
      setLoading(false);
    }
  };

  const onChangeOtp = async (value) => {
    const clean = normalizeOtp(value);
    setOtpCode(clean);
    if (!loading && clean.length === 6) {
      Keyboard.dismiss();
      await submitOtp(clean);
    }
  };

  const canSubmit = !loading && otpCode.length === 6;

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />

      <KeyboardFormScreen contentContainerStyle={styles.scrollContent}>
        <View style={styles.hero}>
          <View style={styles.logoMark}>
            <Text style={styles.logoMarkText}>E</Text>
          </View>
          <Text style={styles.logoText}>ExtraCash</Text>
          <Text style={styles.title}>{title}</Text>
          <Text style={styles.subtitle}>{subtitle}</Text>
        </View>

        <View style={styles.card}>
          <TextInput
            placeholder="Enter OTP"
            placeholderTextColor="#9CA3AF"
            style={styles.input}
            value={otpCode}
            onChangeText={onChangeOtp}
            keyboardType="number-pad"
            returnKeyType="done"
            maxLength={6}
            autoComplete={Platform.OS === 'ios' ? 'one-time-code' : 'sms-otp'}
            textContentType="oneTimeCode"
            autoFocus
            importantForAutofill="yes"
            onSubmitEditing={Keyboard.dismiss}
          />
          <Text style={styles.fieldHint}>Tip: on iPhone, use the “one-time code” suggestion above the keyboard.</Text>

          <TouchableOpacity
            style={[styles.button, (!canSubmit || loading) && styles.buttonDisabled]}
            onPress={() => submitOtp()}
            disabled={!canSubmit || loading}
          >
            <Text style={styles.buttonText}>{loading ? 'Verifying…' : 'Verify & Continue'}</Text>
          </TouchableOpacity>

          <TouchableOpacity style={styles.switchRow} onPress={() => navigation.goBack()} disabled={loading}>
            <Text style={styles.link}>Back</Text>
          </TouchableOpacity>
        </View>

        <Text style={styles.copyright}>© {APP_YEAR} ExtraCash. All rights reserved.</Text>
      </KeyboardFormScreen>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff' },
  scrollContent: {
    flexGrow: 1,
    justifyContent: 'space-between',
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 24,
  },
  hero: { alignItems: 'center', marginBottom: 18 },
  logoMark: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: '#000',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 14,
  },
  logoMarkText: { color: '#fff', fontWeight: '800', fontSize: 26, letterSpacing: -0.5 },
  logoText: { fontSize: 26, fontWeight: '800', color: '#111827', letterSpacing: -0.5 },
  title: { marginTop: 10, fontSize: 16, fontWeight: '800', color: '#111827' },
  subtitle: { marginTop: 6, fontSize: 13, color: '#6B7280', textAlign: 'center' },
  card: {},
  input: {
    borderRadius: 10,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    paddingHorizontal: 14,
    paddingVertical: 14,
    marginBottom: 12,
    color: '#000',
    backgroundColor: '#F9FAFB',
    fontSize: 18,
    letterSpacing: 2,
    textAlign: 'center',
  },
  fieldHint: { fontSize: 11, color: '#9CA3AF', marginTop: -4, marginBottom: 12, paddingHorizontal: 2, textAlign: 'center' },
  button: { backgroundColor: '#000', paddingVertical: 14, borderRadius: 10, alignItems: 'center', marginTop: 4 },
  buttonDisabled: { backgroundColor: '#9CA3AF' },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  switchRow: { flexDirection: 'row', justifyContent: 'center', marginTop: 18 },
  link: { color: '#111827', fontWeight: '700', fontSize: 14 },
  copyright: {
    fontSize: 11,
    color: '#9CA3AF',
    textAlign: 'center',
    marginTop: 16,
  },
});

