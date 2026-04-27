import React, { useEffect } from 'react';
import { SafeAreaView, View, Text, StyleSheet } from 'react-native';
import { StatusBar } from 'expo-status-bar';

export default function SplashIntro({ onDone }) {
  useEffect(() => {
    const timer = setTimeout(() => {
      onDone?.();
    }, 1400);

    return () => clearTimeout(timer);
  }, [onDone]);

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar style="dark" />

      <View style={styles.centerArea}>
        <Text style={styles.brand}>ExtraCash</Text>
      </View>

      <Text style={styles.footer}>© {new Date().getFullYear()} ExtraCash. All rights reserved.</Text>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#fff',
    justifyContent: 'space-between',
    paddingHorizontal: 20,
    paddingBottom: 14,
  },
  centerArea: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  brand: {
    color: '#000',
    fontSize: 42,
    fontWeight: '900',
    letterSpacing: 0.3,
  },
  footer: {
    textAlign: 'center',
    color: '#9CA3AF',
    fontSize: 10,
  },
});
