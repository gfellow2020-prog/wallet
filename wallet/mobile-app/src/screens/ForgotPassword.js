import React, { useState } from 'react';
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, StyleSheet, Keyboard } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';
import { UI } from '../components/ui';

const APP_YEAR = new Date().getFullYear();

export default function ForgotPassword({ navigation }) {
  const { requestPasswordResetOtp } = useAuth();
  const { alert } = useDialog();

  const [identifier, setIdentifier] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!identifier.trim()) {
      return alert({ title: 'Enter phone or email', message: 'Type your phone number or email address.', tone: 'warn' });
    }
    setLoading(true);
    try {
      const res = await requestPasswordResetOtp(identifier.trim());
      if (res?.otp_required && res?.otp?.id && res?.user?.email) {
        navigation.navigate('OtpVerify', {
          email: String(res.user.email).trim().toLowerCase(),
          otpId: Number(res.otp.id),
          purpose: 'password_reset',
        });
        return;
      }

      await alert({
        title: 'Check for OTP',
        message: 'If the account exists, we sent an OTP. Please check your SMS (or email).',
        tone: 'success',
      });
    } catch (err) {
      const msg = err?.response?.data?.message || 'Unable to request OTP right now.';
      await alert({ title: 'Request failed', message: msg, tone: 'danger' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />
      <KeyboardFormScreen contentContainerStyle={styles.scrollContent}>
        <View style={styles.body}>
          <View style={styles.hero}>
            <View style={styles.logoMark}>
              <Text style={styles.logoMarkText}>E</Text>
            </View>
            <Text style={styles.logoText}>ExtraCash</Text>
            <Text style={styles.subtitle}>Reset your password</Text>
          </View>

          <View style={styles.card}>
            <TextInput
              placeholder="Phone number or email"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
              value={identifier}
              onChangeText={setIdentifier}
              keyboardType="default"
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="done"
              onSubmitEditing={Keyboard.dismiss}
            />
            <Text style={styles.fieldHint}>We’ll send an OTP to verify it’s you.</Text>

            <TouchableOpacity
              style={[styles.button, (loading || !identifier.trim()) && styles.buttonDisabled]}
              onPress={submit}
              disabled={loading || !identifier.trim()}
            >
              <Text style={styles.buttonText}>{loading ? 'Sending…' : 'Send OTP'}</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.switchRow} onPress={() => navigation.goBack()} disabled={loading}>
              <Feather name="arrow-left" size={14} color="#111827" />
              <Text style={styles.link}>Back to sign in</Text>
            </TouchableOpacity>
          </View>
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
  body: { flex: 1, justifyContent: 'center', paddingVertical: 24 },
  hero: { alignItems: 'center', marginBottom: 28 },
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
  subtitle: { fontSize: 14, color: '#6B7280', marginTop: 6, textAlign: 'center' },
  card: {},
  input: {
    borderRadius: UI.radius.input,
    borderWidth: 1,
    borderColor: UI.colors.border,
    paddingHorizontal: 14,
    paddingVertical: 14,
    marginBottom: 12,
    color: UI.colors.primary,
    backgroundColor: UI.colors.bg,
    fontSize: 15,
  },
  fieldHint: { fontSize: 11, color: UI.colors.disabled, marginTop: -4, marginBottom: 12, paddingHorizontal: 2 },
  button: { backgroundColor: UI.colors.primary, paddingVertical: 14, borderRadius: UI.radius.input, alignItems: 'center', marginTop: 4 },
  buttonDisabled: { backgroundColor: UI.colors.disabled },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  switchRow: { flexDirection: 'row', gap: 8, justifyContent: 'center', alignItems: 'center', marginTop: 18 },
  link: { color: UI.colors.text, fontWeight: '700', fontSize: 14 },
  copyright: { fontSize: 11, color: UI.colors.disabled, textAlign: 'center', marginTop: 16 },
});

