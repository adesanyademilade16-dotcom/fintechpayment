/* =============================================
   VIRTUAL ACCOUNT DETAILS — app.js (FINAL FIXED PRODUCT WITH COUNTDOWN)
============================================= */

(function () {
  'use strict';

  // Force both URLs to use uppercase 'API/' folder path to prevent 404 errors
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
  let paymentIntervalEngine; // Tracks active countdown runtime loop

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

      // Flexible check for both standard format structures
      if ((resData.status === "success" || resData.success) && resData.data) {
        ACCOUNT.holder = resData.data.accountName || resData.data.account_name || "Adesanya Ibrahim";
        ACCOUNT.bank = resData.data.bankName || resData.data.bank_name || "Wema Bank";
        ACCOUNT.number = resData.data.accountNumber || resData.data.account_number;
        currentAccountRef = resData.data.accountRef || resData.data.account_reference || resData.data.paymentReference;

        updateUIFields();
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #28a745;">✅ Virtual Account Ready. Transfer funds to verify.</span>`;
        }
        showToast("Secure payment details loaded successfully!");

        // Fire off the visual countdown sequence automatically
        const initialSeconds = resData.expirySeconds || (resData.data ? resData.data.expirySeconds : 600);
        executePaymentCountdown(initialSeconds);
      } else {
        throw new Error(resData.message || "Invalid account structure response.");
      }

    } catch (error) {
      console.error("Initialization error:", error);
      if (verificationStatusEl) {
        verificationStatusEl.innerHTML = `
          <span style="color: #dc3545; display: block; margin-bottom: 8px;">❌ Server communication delay.</span>
          <button id="retryInitBtn" style="padding: 6px 12px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Retry Connection</button>
        `;
        const retryBtn = document.getElementById('retryInitBtn');
        if (retryBtn) {
          retryBtn.onclick = () => {
            isFetchingAccount = false;
            initializeVirtualAccount();
          };
        }
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
      const response = await fetch(VERIFY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // To match your PHP backend's expectations:
        body: JSON.stringify({ paymentReference: currentAccountRef })
      });

      if (!response.ok) {
        throw new Error(`HTTP Error ${response.status}`);
      }

      const resData = await response.json();

      if (verificationStatusEl) {
        // Check if backend flags it explicitly as successful validation
        if (resData.status === "success" || resData.success) {
          verificationStatusEl.innerHTML = `<span style="color: #28a745; font-weight: 600;">🎉 Payment Settlement Confirmed & Verified!</span>`;
          showToast("Payment cleared successfully!");
          clearInterval(paymentIntervalEngine); // Stop the visual timer upon successful validation
        } else {
          const msg = resData.message || "No transaction detected yet.";
          verificationStatusEl.innerHTML = `<span style="color: #ffc107; display: block; margin-top: 8px;">⚠️ ${msg}</span>`;
          if (verifyPaymentBtn) verifyPaymentBtn.disabled = false;
          showToast("Transaction status: Pending");
        }
      }

    } catch (error) {
      console.error("Verification processing failed:", error);

      if (verificationStatusEl) {
        verificationStatusEl.innerHTML =
          `<span style="color: #dc3545; display: block; margin-top: 8px;">
            ❌ No payment found yet.
          </span>`;
      }

      if (verifyPaymentBtn) {
        verifyPaymentBtn.disabled = false;
      }

      showToast("No payment found yet.");
    }
  }

  // Standalone payment window countdown engine
  function executePaymentCountdown(secondsRemaining) {
    clearInterval(paymentIntervalEngine);
    const displayWindow = document.getElementById("countdown-text");

    paymentIntervalEngine = setInterval(() => {
      if (secondsRemaining <= 0) {
        clearInterval(paymentIntervalEngine);
        
        if (displayWindow) {
          displayWindow.innerText = "⚠️ This payment window has expired!";
          displayWindow.style.color = "#dc3545";
        }
        
        // Lock out interactions completely
        if (verifyPaymentBtn) {
          verifyPaymentBtn.disabled = true;
          verifyPaymentBtn.innerText = "Session Expired";
          verifyPaymentBtn.style.backgroundColor = "#94a3b8"; 
          verifyPaymentBtn.style.cursor = "not-allowed";
        }
        
        if (verificationStatusEl) {
          verificationStatusEl.innerHTML = `<span style="color: #dc3545; font-weight: 600;">❌ Session Closed. Please refresh the page to request a fresh invoice.</span>`;
        }
        return;
      }

      secondsRemaining--;
      
      let minutes = Math.floor(secondsRemaining / 60);
      let seconds = secondsRemaining % 60;
      
      let formattedMinutes = minutes < 10 ? "0" + minutes : minutes;
      let formattedSeconds = seconds < 10 ? "0" + seconds : seconds;

      if (displayWindow) {
        displayWindow.innerText = `Account expires in ${formattedMinutes}:${formattedSeconds} minutes`;
      }
    }, 1000);
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
