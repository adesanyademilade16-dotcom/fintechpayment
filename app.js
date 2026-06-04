/* =============================================
   VIRTUAL ACCOUNT DETAILS — app.js (FIXED PATHS)
============================================= */

(function () {
  'use strict';

  // CRITICAL FIX: Both URLs must use uppercase API to match your server environment
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

  // Hardcoded initial fallback values
  let ACCOUNT = { holder: 'Adesanya Ibrahim', bank: 'Wema Bank', number: '7748711117' };
  
  // CRITICAL FIX: Ensure this tracks your active Monnify reference globally
  let currentAccountRef = ''; 

  let toastTimer;
  function showToast(message) {
    if (!toast || !toastText) return;
    clearTimeout(toastTimer);
    toastText.textContent = message;
    toast.classList.add('show');
    toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 2500);
  }

  // 1. Fetch and create the account on load
  async function initAccount() {
    try {
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customerName: "Adesanya Ibrahim",
          customerEmail: "adesanya@example.com"
        })
      });

      const result = await response.json();
      
      if (result.status === 'success' || result.success) {
        const data = result.data;
        ACCOUNT.holder = data.accountName;
        ACCOUNT.bank = data.bankName;
        ACCOUNT.number = data.accountNumber;
        currentAccountRef = data.accountRef; // Save the real reference for verification!

        // Update UI Fields dynamically
        if(holderField) holderField.textContent = ACCOUNT.holder;
        if(bankField) bankField.textContent = ACCOUNT.bank;
        if(accountField) accountField.textContent = ACCOUNT.number;
        
        showToast("Virtual account loaded successfully");
      }
    } catch (error) {
      console.error("Account Initialization Failed:", error);
      showToast("Using local fallback credentials");
    }
  }

  // 2. Verification Function triggered by Button Click
  async function verifySettlement() {
    if (!currentAccountRef) {
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `<span style="color: #ef4444;">Error: No active account reference found to verify.</span>`;
      }
      return;
    }

    if (verificationStatusEl) {
      verificationStatusEl.innerHTML = `<span style="color: #3b82f6;">Verifying with Monnify...</span>`;
    }

    try {
      const response = await fetch(VERIFY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accountReference: currentAccountRef })
      });

      const result = await response.json();

      if (verificationStatusEl) {
        if (result.status === 'success' || result.success) {
          verificationStatusEl.innerHTML = `<span style="color: #10b981; font-weight:700;">✅ Payment Verified & Settled successfully!</span>`;
          showToast("Payment Confirmed!");
        } else {
          // Handles 'PENDING' or 'NOT FOUND' gracefully from Monnify's API
          const msg = result.message || "Payment not found or still pending.";
          verificationStatusEl.innerHTML = `<span style="color: #f59e0b;">Status: ${msg}</span>`;
        }
      }
    } catch (error) {
      console.error("Network verification error:", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `<span style="color: #ef4444;">Network connection error. Try again.</span>`;
      }
    }
  }

  // Bind UI interactive event listeners cleanly
  copyButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const val = btn.previousElementSibling?.querySelector('.field-value')?.textContent || btn.dataset.copy;
      navigator.clipboard.writeText(val);
      showToast(`${btn.dataset.label || 'Details'} copied`);
    });
  });

  if (copyAllBtn) {
    copyAllBtn.addEventListener('click', () => {
      navigator.clipboard.writeText(`Account Holder: ${ACCOUNT.holder}\nBank Name: ${ACCOUNT.bank}\nAccount Number: ${ACCOUNT.number}`);
      showToast('All data copied to clipboard');
    });
  });

  // Attach click handler safely to button element
  if (verifyPaymentBtn) {
    verifyPaymentBtn.onclick = verifySettlement;
  }

  // Self-execute account generation immediately on view initialization
  initAccount();

})();
