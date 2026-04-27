import React, { useState } from 'react';
import { SafeAreaView, View, Text, TextInput, TouchableOpacity, StyleSheet, Keyboard } from 'react-native';
import { Feather } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import { useAuth } from '../context/AuthContext';
import { useDialog } from '../context/DialogContext';
import KeyboardFormScreen from '../components/KeyboardFormScreen';
import { UI } from '../components/ui';

const APP_YEAR = new Date().getFullYear();

export default function Login({ navigation }) {
  const { login } = useAuth();
  const { alert } = useDialog();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [agreed, setAgreed] = useState(false);
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!email || !password) return alert({ title: 'Missing info', message: 'Please fill in all fields.', tone: 'warn' });
    if (!agreed) {
      return alert({
        title: 'Accept Terms first',
        message: 'You need to agree to the Terms & Conditions to continue.',
        tone: 'warn',
      });
    }
    setLoading(true);
    try {
      await login(email.trim().toLowerCase(), password);
    } catch (err) {
      const status = err?.response?.status;
      const data = err?.response?.data;
      if (status === 428 && data?.otp_required && data?.otp?.id) {
        navigation.navigate('OtpVerify', {
          email: email.trim().toLowerCase(),
          otpId: Number(data.otp.id),
          purpose: String(data?.otp?.purpose || 'login'),
        });
        return;
      }

      const msg = err?.response?.data?.message
        || (err?.request ? 'Cannot reach API server. Check network/hotspot and try again.' : null)
        || err?.message
        || 'Invalid credentials';
      await alert({ title: 'Login failed', message: msg, tone: 'danger' });
    } finally {
      setLoading(false);
    }
  };

  const canSubmit = !!email && !!password && agreed && !loading;

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
            <Text style={styles.subtitle}>Sign in to continue to your wallet</Text>
          </View>

          <View style={styles.card}>
            <TextInput
              placeholder="Email address"
              placeholderTextColor="#9CA3AF"
              style={styles.input}
              value={email}
              onChangeText={setEmail}
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              returnKeyType="next"
              blurOnSubmit={false}
            />
            <Text style={styles.fieldHint}>Use the email you registered with.</Text>

            <View style={styles.passwordWrap}>
              <TextInput
                placeholder="Password"
                placeholderTextColor="#9CA3AF"
                style={[styles.input, styles.passwordInput]}
                secureTextEntry={!showPassword}
                value={password}
                onChangeText={setPassword}
                returnKeyType="done"
                onSubmitEditing={Keyboard.dismiss}
              />
              <TouchableOpacity
                style={styles.passwordToggle}
                onPress={() => setShowPassword((v) => !v)}
                activeOpacity={0.75}
                accessibilityRole="button"
                accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}
              >
                <Feather name={showPassword ? 'eye-off' : 'eye'} size={18} color={UI.colors.muted} />
              </TouchableOpacity>
            </View>
            <Text style={styles.fieldHint}>Enter your account password.</Text>

            <TouchableOpacity
              style={styles.consentRow}
              onPress={() => setAgreed((v) => !v)}
              activeOpacity={0.7}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: agreed }}
            >
              <View style={[styles.checkbox, agreed && styles.checkboxChecked]}>
                {agreed ? <Feather name="check" size={14} color="#fff" /> : null}
              </View>
              <Text style={styles.consentText}>
                I agree to the{' '}
                <Text style={styles.consentLink}>Terms & Conditions</Text> and{' '}
                <Text style={styles.consentLink}>Privacy Policy</Text>.
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.button, !canSubmit && styles.buttonDisabled]}
              onPress={submit}
              disabled={!canSubmit}
            >
              <Text style={styles.buttonText}>{loading ? 'Signing in…' : 'Sign in'}</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.forgotRow} onPress={() => navigation.navigate('ForgotPassword')}>
              <Text style={styles.forgotText}>Forgot password?</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.switchRow} onPress={() => navigation.navigate('Register')}>
              <Text style={styles.switchText}>Don't have an account? </Text>
              <Text style={styles.link}>Create one</Text>
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
  body: {
    flex: 1,
    justifyContent: 'center',
    paddingVertical: 24,
  },
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
  passwordWrap: { position: 'relative' },
  passwordInput: { paddingRight: 44 },
  passwordToggle: {
    position: 'absolute',
    right: 10,
    top: 0,
    bottom: 0,
    width: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  fieldHint: { fontSize: 11, color: UI.colors.disabled, marginTop: -4, marginBottom: 12, paddingHorizontal: 2 },
  consentRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    paddingVertical: 6,
    marginTop: 4,
    marginBottom: 14,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 5,
    borderWidth: 1.5,
    borderColor: '#9CA3AF',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
    backgroundColor: '#fff',
  },
  checkboxChecked: { backgroundColor: '#111827', borderColor: '#111827' },
  consentText: { flex: 1, fontSize: 12, color: '#4B5563', lineHeight: 18 },
  consentLink: { color: '#111827', fontWeight: '700' },
  button: {
    backgroundColor: UI.colors.primary,
    paddingVertical: 14,
    borderRadius: UI.radius.input,
    alignItems: 'center',
    marginTop: 4,
  },
  buttonDisabled: { backgroundColor: UI.colors.disabled },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  forgotRow: { alignItems: 'center', marginTop: 14 },
  forgotText: { color: UI.colors.text, fontWeight: '700', fontSize: 13 },
  switchRow: { flexDirection: 'row', justifyContent: 'center', marginTop: 20 },
  switchText: { color: '#6B7280', fontSize: 14 },
  link: { color: '#111827', fontWeight: '700', fontSize: 14 },
  copyright: {
    fontSize: 11,
    color: '#9CA3AF',
    textAlign: 'center',
    marginTop: 16,
  },
});
