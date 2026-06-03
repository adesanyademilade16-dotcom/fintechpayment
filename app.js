/* =============================================
   VIRTUAL ACCOUNT DETAILS — app.js (FINAL RESOLUTION)
============================================= */

(function () {
  'use strict';

  // FIXED: Force both URLs to use uppercase 'API/' folder path to prevent 404 errors
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

  // Core state management
  let ACCOUNT = { holder: '', bank: '', number: '' };
  let currentAccountRef = '';
  let isFetchingAccount = false;

  let toastTimer;
  function showToast(message) {
    if (!toast || !toastText) return;
    clearTimeout(toastTimer);
    toastText.textContent = message;
    toast.classList.add('show');
    toastTimer = setTimeout(() => { toast.classList.remove('show'); }, 3000);
  }

  // Copy management
  async function copyText(text, successMessage) {
    if (!text || text.trim() === "") {
      showToast("No active data to copy yet.");
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
      showToast("Copy failed. Please copy manually.");
    }
  }

  function updateUIFields() {
    if (holderField) holderField.textContent = ACCOUNT.holder || "Loading...";
    if (bankField) bankField.textContent = ACCOUNT.bank || "Loading...";
    if (accountField) accountField.textContent = ACCOUNT.number || "Loading...";
  }

  // 1. Fetch virtual account details
  async function initializeVirtualAccount() {
    if (isFetchingAccount) return;
    isFetchingAccount = true;

    if (verificationStatusEl) {
      verificationStatusEl.innerHTML = `<span style="color: #666;">⏳ Connecting to secure banking network...</span>`;
    }
    updateUIFields();

    try {
      const response = await fetch(API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customerName: "Adesanya Ibrahim",
          customerEmail: "adesanya@example.com"
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP Error ${response.status}`);
      }

      const resData = await response.json();

      if (resData.status === "success" && resData.data) {
        ACCOUNT.holder = resData.data.accountName || "Adesanya Ibrahim";
        ACCOUNT.bank = resData.data.bankName || "Wema Bank";
        ACCOUNT.number = resData.data.accountNumber;
        currentAccountRef = resData.data.accountRef;

        updateUIFields();
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #28a745;">✅ Virtual Account Ready. Transfer test funds now.</span>`;
        }
        showToast("Secure payment details loaded successfully!");
      } else {
        throw new Error(resData.message || "Invalid account structure response.");
      }

    } catch (error) {
      console.error("Initialization error:", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `
          <span style="color: #dc3545; display: block; margin-bottom: 8px;">❌ Server cold start or network delay.</span>
          <button id="retryInitBtn" style="padding: 6px 12px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Retry Connection</button>
        `;
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

  // 2. Verify payment settlement against the server
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
      // Hits 'API/verify_payment.php' securely using POST
      const response = await fetch(VERIFY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accountReference: currentAccountRef })
      });

      if (!response.ok) {
        throw new Error(`HTTP Error ${response.status}`);
      }

      const resData = await response.json();

      if (resData.status === "success" && resData.data) {
        // Evaluate typical Monnify statuses
        if (resData.data.status === "PAID" || resData.data.status === "SETTLED" || resData.data.status === "OVERPAID") {
          if (verificationStatusEl) {
            verificationStatusEl.innerHTML = `<span style="color: #28a745; font-weight: 600;">🎉 Payment Settlement Confirmed & Verified!</span>`;
          }
          showToast("Payment cleared successfully!");
        } else {
          if (verificationStatusEl) {
            verificationStatusEl.innerHTML = `<span style="color: #ffc107; display: block; margin-top: 8px;">⚠️ No transaction detected yet. Make sure you trigger the mock transfer on your Monnify Sandbox Dashboard.</span>`;
          }
          if (verifyPaymentBtn) verifyPaymentBtn.disabled = false;
          showToast("Transaction status: Pending");
        }
      } else {
        throw new Error(resData.message || "Invalid payload verification structure.");
      }

    } catch (error) {
      console.error("Verification processing failed:", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `<span style="color: #dc3545; display: block; margin-top: 8px;">❌ Server verification endpoint unavailable (HTTP 404/500). Please check your file path alignment.</span>`;
      }
      if (verifyPaymentBtn) verifyPaymentBtn.disabled = false;
      showToast("Payment network validation timeout.");
    }
  }

  // Bind actions
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
      if (!ACCOUNT.number) return showToast("No active details to copy.");
      await copyText(`Account Holder: ${ACCOUNT.holder}\nBank Name: ${ACCOUNT.bank}\nAccount Number: ${ACCOUNT.number}`, 'All account properties copied');
    });
  }

  if (shareBtn) {
    shareBtn.addEventListener('click', async () => {
      if (!ACCOUNT.number) return showToast("No details available to share.");
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

  // Auto-run script configuration initialization
  if (document.readyState === "interactive" || document.readyState === "complete") {
    initializeVirtualAccount();
  } else {
    document.addEventListener('DOMContentLoaded', initializeVirtualAccount);
  }

})();
