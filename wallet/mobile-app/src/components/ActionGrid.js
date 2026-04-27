import React from 'react';
import { View, TouchableOpacity, Text, StyleSheet } from 'react-native';

export default function ActionGrid({ onFund, onSend, onPay, onHistory }){
  return (
    <View style={styles.grid}>
      <TouchableOpacity style={styles.item} onPress={onFund}>
        <View style={styles.iconBox}><Text style={{color:'#fff'}}>+</Text></View>
        <Text style={styles.label}>Fund</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.item} onPress={onSend}>
        <View style={styles.iconBox}><Text style={{color:'#fff'}}>⇄</Text></View>
        <Text style={styles.label}>Send</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.item} onPress={onPay}>
        <View style={styles.iconBox}><Text style={{color:'#fff'}}>✔</Text></View>
        <Text style={styles.label}>Pay</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.item} onPress={onHistory}>
        <View style={styles.iconBox}><Text style={{color:'#fff'}}>⟳</Text></View>
        <Text style={styles.label}>History</Text>
      </TouchableOpacity>
    </View>
  )
}

const styles = StyleSheet.create({
  grid: { flexDirection:'row', justifyContent:'space-between', marginBottom:12 },
  item: { alignItems:'center', width:'22%' },
  iconBox: { height:60, width:60, borderRadius:12, backgroundColor:'#000', alignItems:'center', justifyContent:'center' },
  label: { marginTop:8, color:'#111827', fontWeight:'700' }
});
