/* =============================================
   VIRTUAL ACCOUNT DETAILS — app.js (UPGRADED)
============================================= */

(function () {
  'use strict';

  // Matches the successful uppercase routing paths seen in Render logs
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

  // Track the actual live account details state (Starts empty, no phantom mock data)
  let ACCOUNT = { holder: '', bank: '', number: '' };
  let currentAccountRef = '';
  let isFetchingAccount = false;

  let toastTimer;
  function showToast(message) {
    if (!toast || !toastText) return;
    clearTimeout(toastTimer);
    toastText.textContent = message;
    toast.classList.add('show');
    toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 2500);
  }

  // Safely copy data to clipboard
  async function copyText(text, successMessage) {
    if (!text || text.trim() === "") {
      showToast("No data available to copy yet.");
      return;
    }
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        showToast(successMessage);
      } else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast(successMessage);
      }
    } catch (err) {
      showToast("Copy failed. Please select and copy manually.");
    }
  }

  // Update UI text safely
  function updateUIFields() {
    if (holderField) holderField.textContent = ACCOUNT.holder || "Loading...";
    if (bankField) bankField.textContent = ACCOUNT.bank || "Loading...";
    if (accountField) accountField.textContent = ACCOUNT.number || "Loading...";
  }

  // Step 1: Initialize and request the virtual account from the server cleanly
  async function initializeVirtualAccount() {
    if (isFetchingAccount) return;
    isFetchingAccount = true;

    if (verificationStatusEl) {
      verificationStatusEl.innerHTML = `<span style="color: #666;">⏳ Waking up secure gateway server... Please wait up to 60 seconds.</span>`;
    }
    updateUIFields();

    try {
      // Send payload data to your create account script
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customerName: "Adesanya Ibrahim",
          customerEmail: "adesanya@example.com"
        })
      });

      if (!response.ok) {
        throw new Error(`Server status handling error: ${response.status}`);
      }

      const resData = await response.json();

      // Check if server script passed structured database/Monnify array
      if (resData.status === "success" && resData.data) {
        ACCOUNT.holder = resData.data.accountName || "Adesanya Ibrahim";
        ACCOUNT.bank = resData.data.bankName || "Wema Bank";
        ACCOUNT.number = resData.data.accountNumber || "7748711117";
        currentAccountRef = resData.data.accountRef || "";

        updateUIFields();
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #28a745;">✅ Virtual Account Ready. Transfer test funds now.</span>`;
        }
        showToast("Secure payment details loaded successfully!");
      } else {
        throw new Error(resData.message || "Invalid account body format structure.");
      }

    } catch (error) {
      console.error("Account Initialization Failed:", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `
          <span style="color: #dc3545; display: block; margin-bottom: 8px;">❌ Connection interrupted or timed out.</span>
          <button id="retryInitBtn" style="padding: 6px 12px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Retry Connection</button>
        `;
        // Bind event on dynamically injected retry button
        document.getElementById('retryInitBtn').onclick = () => {
          isFetchingAccount = false;
          initializeVirtualAccount();
        };
      }
      showToast("Failed to initialize server connection.");
    } finally {
      isFetchingAccount = false;
    }
  }

  // Step 2: Query the payment settlement validation API route
  async function verifySettlement() {
    if (!currentAccountRef) {
      showToast("Cannot verify payment without an active account token.");
      return;
    }

    if (verifyPaymentBtn) verifyPaymentBtn.disabled = true;
    if (verificationStatusEl) {
      verificationStatusEl.innerHTML = `<span>⚡ Contacting clearing networks to verify transaction settlement...</span>`;
    }

    try {
      const response = await fetch(VERIFY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accountReference: currentAccountRef })
      });

      if (!response.ok) {
        throw new Error(`Network verification flag dropped: ${response.status}`);
      }

      const resData = await response.json();

      if (resData.status === "success" && resData.data) {
        if (resData.data.status === "PAID" || resData.data.status === "SETTLED") {
          if (verificationStatusEl) {
            verificationStatusEl.innerHTML = `<span style="color: #28a745; font-weight: 600;">🎉 Payment Settlement Confirmed & Verified! Ticket: ${resData.data.paymentReference || 'N/A'}</span>`;
          }
          showToast("Payment cleared successfully!");
        } else {
          if (verificationStatusEl) {
            verificationStatusEl.innerHTML = `<span style="color: #ffc107;">⚠️ Payment status pending. Please try again in a moment.</span>`;
          }
          if (verifyPaymentBtn) verifyPaymentBtn.disabled = false;
        }
      } else {
        throw new Error(resData.message || "Verification payload corrupted.");
      }

    } catch (error) {
      console.error("Verification processing failed:", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `<span style="color: #dc3545;">❌ Connection interrupted during clearing check. Try checking again.</span>`;
      }
      if (verifyPaymentBtn) verifyPaymentBtn.disabled = false;
      showToast("Payment network validation timeout.");
    }
  }

  // Bind Standard Actions
  copyButtons.forEach(btn => {
    btn.addEventListener('click', async () => {
      let targetValue = '';
      if (btn.dataset.copy === 'holder') targetValue = ACCOUNT.holder;
      if (btn.dataset.copy === 'bank') targetValue = ACCOUNT.bank;
      if (btn.dataset.copy === 'number') targetValue = ACCOUNT.number;

      await copyText(targetValue || btn.dataset.copy, `${btn.dataset.label || 'Value'} copied to clipboard`);
    });
  });

  if (copyAllBtn) {
    copyAllBtn.addEventListener('click', async () => {
      if (!ACCOUNT.number) {
        showToast("No active data loaded to copy.");
        return;
      }
      await copyText(`Account Holder: ${ACCOUNT.holder}\nBank Name: ${ACCOUNT.bank}\nAccount Number: ${ACCOUNT.number}`, 'All account properties copied');
    });
  }

  if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
      if (!ACCOUNT.number) {
        showToast("No active details to share.");
        return;
      }
      const text = `Virtual Account Details\n\nAccount Holder: ${ACCOUNT.holder}\nBank: ${ACCOUNT.bank}\nAccount Number: ${ACCOUNT.number}`;
      if (navigator.share) {
        try { await navigator.share({ title: 'Virtual Account Data', text: text }); } catch (e) {}
      } else {
        await copyText(text, 'Account data copied for sharing');
      }
    });
  }

  if (backBtn) {
    backBtn.addEventListener('click', () => {
      window.history.length > 1 ? window.history.back() : (window.location.href = '/');
    });
  }

  if (verifyPaymentBtn) {
    verifyPaymentBtn.onclick = verifySettlement;
  }

  // Auto-run script configuration when DOM setup is structurally finalized
  document.addEventListener('DOMContentLoaded', initializeVirtualAccount);
  
  // Fallback trigger if the DOMContentLoaded event was already executed
  if (document.readyState === "interactive" || document.readyState === "complete") {
    initializeVirtualAccount();
  }

})();
