import React, { useMemo, useState } from 'react';
import { SafeAreaView, View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { StatusBar } from 'expo-status-bar';

const tips = [
  {
    title: 'Your Wallet Hub',
    description: 'Track your balance, view card details, and monitor transactions in one clean place.',
  },
  {
    title: 'Buy & Sell Nearby',
    description: 'Discover products around you and post your listings directly from the app.',
  },
  {
    title: 'Stay Verified & Secure',
    description: 'Complete NRC verification and keep your profile compliant for safer payments.',
  },
];

export default function Onboarding({ onDone }) {
  const [step, setStep] = useState(0);
  const tip = tips[step];

  const progressDots = useMemo(
    () => tips.map((_, index) => <View key={`dot-${index}`} style={[styles.dot, step === index && styles.dotActive]} />),
    [step],
  );

  const next = () => {
    if (step >= tips.length - 1) {
      onDone?.();
      return;
    }
    setStep((s) => s + 1);
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />

      <View style={styles.centerArea}>
        <Text style={styles.brand}>ExtraCash</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.stepLabel}>Tip {step + 1} of {tips.length}</Text>
        <Text style={styles.tipTitle}>{tip.title}</Text>
        <Text style={styles.tipBody}>{tip.description}</Text>

        <View style={styles.dotsRow}>{progressDots}</View>

        <TouchableOpacity style={styles.primaryBtn} onPress={next}>
          <Text style={styles.primaryBtnText}>{step === tips.length - 1 ? 'Get started' : 'Next tip'}</Text>
        </TouchableOpacity>

        {step < tips.length - 1 && (
          <TouchableOpacity style={styles.skipBtn} onPress={onDone}>
            <Text style={styles.skipBtnText}>Skip</Text>
          </TouchableOpacity>
        )}
      </View>

      <Text style={styles.footer}>© {new Date().getFullYear()} ExtraCash. All rights reserved.</Text>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    paddingHorizontal: 22,
    paddingBottom: 14,
  },
  centerArea: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    minHeight: 280,
  },
  brand: {
    color: '#000',
    fontSize: 40,
    fontWeight: '900',
    letterSpacing: 0.3,
  },
  card: {
    borderWidth: 1,
    borderColor: '#E5E7EB',
    borderRadius: 14,
    padding: 16,
    backgroundColor: '#fff',
  },
  stepLabel: {
    fontSize: 11,
    color: '#6B7280',
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: 8,
  },
  tipTitle: {
    fontSize: 20,
    color: '#111827',
    fontWeight: '800',
    marginBottom: 8,
  },
  tipBody: {
    fontSize: 14,
    color: '#4B5563',
    lineHeight: 21,
  },
  dotsRow: {
    flexDirection: 'row',
    gap: 7,
    marginTop: 16,
    marginBottom: 16,
    alignItems: 'center',
  },
  dot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: '#D1D5DB',
  },
  dotActive: {
    width: 18,
    backgroundColor: '#111827',
  },
  primaryBtn: {
    backgroundColor: '#000',
    borderRadius: 10,
    paddingVertical: 13,
    alignItems: 'center',
  },
  primaryBtnText: {
    color: '#fff',
    fontWeight: '700',
    fontSize: 15,
  },
  skipBtn: {
    marginTop: 10,
    alignItems: 'center',
  },
  skipBtnText: {
    color: '#6B7280',
    fontSize: 13,
    fontWeight: '600',
  },
  footer: {
    textAlign: 'center',
    color: '#9CA3AF',
    fontSize: 10,
    marginTop: 10,
  },
});
