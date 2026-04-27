import React, { useMemo, useRef, useState } from 'react';
import { SafeAreaView, View, Text, TouchableOpacity, StyleSheet, ScrollView, useWindowDimensions } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { Feather } from '@expo/vector-icons';

const tips = [
  {
    icon: 'credit-card',
    title: 'Your wallet, all in one place',
    description: 'See your balance, track spending, and keep every payment history organized and easy to review.',
  },
  {
    icon: 'shopping-bag',
    title: 'Buy and sell around you',
    description: 'Discover nearby listings, connect with people in your area, and post your own products in minutes.',
  },
  {
    icon: 'shield',
    title: 'Secure and compliant',
    description: 'Verify identity with NRC and keep your account trusted for safer, smoother transactions every day.',
  },
];

export default function TipsOnboarding({ onFinish }) {
  const { width } = useWindowDimensions();
  const scrollRef = useRef(null);
  const [step, setStep] = useState(0);

  const pages = useMemo(
    () => tips.map((tip, index) => (
      <View key={`tip-${index}`} style={[styles.page, { width: width - 44 }]}>
        <View style={styles.vectorWrap}>
          <View style={styles.vectorCircle}>
            <Feather name={tip.icon} size={40} color="#111827" />
          </View>
        </View>

        <Text style={styles.title}>{tip.title}</Text>
        <Text style={styles.description}>{tip.description}</Text>
      </View>
    )),
    [width],
  );

  const goTo = (index) => {
    scrollRef.current?.scrollTo({ x: index * (width - 44), animated: true });
    setStep(index);
  };

  const next = () => {
    if (step >= tips.length - 1) {
      onFinish?.();
      return;
    }
    goTo(step + 1);
  };

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />

      <View style={styles.topRow}>
        <Text style={styles.brand}>ExtraCash</Text>
        <TouchableOpacity onPress={onFinish}>
          <Text style={styles.skip}>Skip</Text>
        </TouchableOpacity>
      </View>

      <ScrollView
        ref={scrollRef}
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        decelerationRate="fast"
        onMomentumScrollEnd={(e) => {
          const pageWidth = width - 44;
          const index = Math.round(e.nativeEvent.contentOffset.x / pageWidth);
          setStep(index);
        }}
        contentContainerStyle={styles.pagesWrap}
      >
        {pages}
      </ScrollView>

      <View style={styles.bottomArea}>
        <View style={styles.dotsRow}>
          {tips.map((_, i) => (
            <View key={`dot-${i}`} style={[styles.dot, i === step && styles.dotActive]} />
          ))}
        </View>

        <TouchableOpacity style={styles.primaryBtn} onPress={next}>
          <Text style={styles.primaryBtnText}>{step === tips.length - 1 ? 'Continue to Login' : 'Next'}</Text>
        </TouchableOpacity>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    paddingHorizontal: 22,
    paddingTop: 10,
    paddingBottom: 20,
  },
  topRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 14,
  },
  brand: {
    fontSize: 20,
    fontWeight: '800',
    color: '#111827',
  },
  skip: {
    fontSize: 13,
    color: '#6B7280',
    fontWeight: '700',
  },
  pagesWrap: {
    alignItems: 'stretch',
  },
  page: {
    flex: 1,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: '#E5E7EB',
    padding: 22,
    marginRight: 0,
    backgroundColor: '#fff',
  },
  vectorWrap: {
    alignItems: 'center',
    marginBottom: 20,
    marginTop: 8,
  },
  vectorCircle: {
    height: 110,
    width: 110,
    borderRadius: 55,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#E5E7EB',
  },
  title: {
    fontSize: 24,
    color: '#111827',
    fontWeight: '800',
    textAlign: 'center',
    marginBottom: 10,
  },
  description: {
    fontSize: 15,
    lineHeight: 23,
    textAlign: 'center',
    color: '#4B5563',
  },
  bottomArea: {
    marginTop: 14,
  },
  dotsRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
    marginBottom: 14,
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
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryBtnText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '700',
  },
});
