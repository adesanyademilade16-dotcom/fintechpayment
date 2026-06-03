/* =============================================
   VIRTUAL ACCOUNT DETAILS — Production Sync
============================================= */

(function () {
  'use strict';

  // Explicit paths matching your live directory configuration
  const API_URL = 'API/create_account.php';
  const VERIFY_URL = 'API/verify_payment.php';

  const toast = document.getElementById('toast');
  const toastText = document.getElementById('toastText');
  const backBtn = document.getElementById('backBtn');
  const copyAllBtn = document.getElementById('copyAllBtn');
  const shareBtn = document.getElementById('sharePdfBtn');

  const holderField = document.querySelector('#field-name .field-value');
  const bankField = document.querySelector('#field-bank .field-value');
  const accountField = document.querySelector('#field-number .field-value');
  const copyButtons = document.querySelectorAll('.copy-field-btn');

  const verifyPaymentBtn = document.getElementById('verifyPaymentBtn');
  const verificationStatusEl = document.getElementById('verificationStatus');

  let ACCOUNT = { holder: 'Loading node...', bank: 'Connecting API...', number: '------------' };
  let currentAccountRef = '';

  let toastTimer;
  function showToast(message) {
    if (!toast || !toastText) return;
    clearTimeout(toastTimer);
    toastText.textContent = message;
    toast.classList.add('show');
    toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 2500);
  }

  async function copyText(text, message) {
    try {
      await navigator.clipboard.writeText(text);
      showToast(message);
    } catch (err) {
      const textarea = document.createElement('textarea');
      textarea.value = text;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      textarea.remove();
      showToast(message);
    }
  }

  function formatAccountNumber(num) {
    if (!num || num === '------------') return '------------';
    return num.toString().replace(/(\d{4})(?=\d)/g, '$1 ');
  }

  function updateUI(data) {
    if (!data) return;

    // Direct mapping to the Monnify Client array payload fields
    ACCOUNT = {
      holder: data.accountName || 'Adesanya Ibrahim',
      bank: data.bankName || 'Virtual Bank Node',
      number: data.accountNumber || '------------'
    };

    if (holderField) holderField.textContent = ACCOUNT.holder;
    if (bankField) bankField.textContent = ACCOUNT.bank;
    if (accountField) accountField.textContent = formatAccountNumber(ACCOUNT.number);

    copyButtons.forEach(btn => {
      const label = btn.dataset.label;
      if (label === 'Account holder name') btn.dataset.copy = ACCOUNT.holder;
      if (label === 'Bank name') btn.dataset.copy = ACCOUNT.bank;
      if (label === 'Account number') btn.dataset.copy = ACCOUNT.number;
    });
  }

  async function loadAccount() {
    try {
      const res = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
      });

      const result = await res.json();

      if (result && result.status === 'success') {
        // Core structural fix: safely extracting properties from the inner 'data' block
        const targetPayload = result.data || result;
        currentAccountRef = targetPayload.accountRef || 'REF_' + Date.now();
        
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = '';
        }
        updateUI(targetPayload);
      } else {
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #ef4444; font-weight:600;">❌ Verification Node Unready</span>`;
        }
        showToast(result.message || "Failed to structure virtual nodes.");
      }
    } catch (error) {
      console.error('API Sync Handshake failure:', error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `<span style="color: #ef4444; font-weight:600;">⚠️ API Configuration Mismatch</span>`;
      }
    }
  }

  async function verifyPaymentAlert() {
    if (!currentAccountRef) {
      showToast("Cannot verify an uninitialized account state.");
      return;
    }

    if (verificationStatusEl) {
      verificationStatusEl.innerHTML = `<span style="color: #2563eb; font-weight:600;">🔄 Quering gateway ledgers...</span>`;
    }

    try {
      const response = await fetch(VERIFY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accountReference: currentAccountRef })
      });
      
      const result = await response.json();
      
      if (result && result.status === 'success') {
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #10b981; font-weight:600;">✅ Payment Verified & Settled!</span>`;
        }
        showToast("System credit processed!");
      } else {
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #ef4444; font-weight:600;">❌ Payment Not Received Yet</span>`;
        }
        showToast(result.message || "Still waiting for payment...");
      }
    } catch (error) {
      console.error("Verification connection error", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `<span style="color: #ef4444; font-weight:600;">⚠️ Connection Interrupted</span>`;
      }
    }
  }

  // Set Event Bindings
  copyButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      if (ACCOUNT.number === '------------') return;
      await copyText(btn.dataset.copy || '', `${btn.dataset.label || 'Value'} copied`);
    });
  });

  if (copyAllBtn) {
    copyAllBtn.addEventListener('click', async () => {
      if (ACCOUNT.number === '------------') return;
      await copyText(`Account Holder: ${ACCOUNT.holder}\nBank Name: ${ACCOUNT.bank}\nAccount Number: ${ACCOUNT.number}`, 'All data saved to clipboard');
    });
  }

  if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
      if (ACCOUNT.number === '------------') return;
      const text = `Virtual Account Details\n\nAccount Holder: ${ACCOUNT.holder}\nBank: ${ACCOUNT.bank}\nAccount Number: ${ACCOUNT.number}`;
      if (navigator.share) {
        try { await navigator.share({ title: 'Payment Node Data', text: text }); } catch (e) {}
      } else {
        await copyText(text, 'Copied details for sharing');
      }
    });
  }

  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.history.length > 1 ? window.history.back() : (window.location.href = '/');
    });
  }

  if (verifyPaymentBtn) {
    verifyPaymentBtn.onclick = verifyPaymentAlert;
  }
  
  // Fire Initialization
  loadAccount();
})();
