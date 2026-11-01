<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Contact the CampusTransit WUM transport office for routes, timings, bus passes and feedback.">
  <title>Contact Us | CampusTransit WUM</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

  <style>
    .top-social a { display: inline-flex; align-items: center; justify-content: center; }
    .top-social svg { width: 16px; height: 16px; fill: currentColor; }

    .page-hero {
      background: radial-gradient(circle at 90% 10%, rgba(30, 123, 224, 0.35), transparent 40%),
                  linear-gradient(120deg, #083c87, #0b4fa8);
      color: #fff;
      padding: 3.5rem 0 6rem;
    }
    .page-eyebrow { color: #bfdbfe; font-size: 0.82rem; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 0.6rem; }
    .page-hero h1 { color: #fff; font-size: clamp(2rem, 4.6vw, 3.2rem); line-height: 1.1; text-transform: uppercase; margin-bottom: 0.8rem; }
    .page-hero p { color: #dbeafe; max-width: 640px; margin: 0; font-size: 1.02rem; }

    .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: -3.5rem; position: relative; z-index: 2; }
    .info-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.2rem; display: flex; gap: 0.9rem; align-items: flex-start; }
    .info-ico { flex: 0 0 42px; height: 42px; border-radius: 10px; background: var(--tint); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .info-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); margin: 0 0 0.2rem; }
    .info-value { font-weight: 700; color: var(--text); margin: 0; overflow-wrap: anywhere; }

    .contact-section { padding: 3.5rem 0; }
    .contact-grid { display: grid; grid-template-columns: 1.1fr 1fr; gap: 1.8rem; align-items: start; }
    .form-card, .map-panel { background: #fff; border-radius: 16px; box-shadow: var(--shadow); padding: 1.8rem; }
    .form-card h2, .map-panel h2 { font-size: 1.5rem; margin-bottom: 0.3rem; }
    .form-sub { color: var(--muted); margin-bottom: 1.2rem; font-size: 0.92rem; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .field { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1rem; }
    .field label { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--muted); }
    .field input, .field select, .field textarea {
      width: 100%; padding: 0.75rem 0.9rem; border: 1.5px solid var(--border);
      border-radius: 10px; font: inherit; color: var(--text); background: #fff;
    }
    .field textarea { min-height: 130px; resize: vertical; }
    .field input:focus, .field select:focus, .field textarea:focus {
      outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(30, 123, 224, 0.18);
    }
    .count { align-self: flex-end; font-size: 0.78rem; color: var(--muted); }
    .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

    .submit-btn {
      width: 100%; padding: 0.9rem 1.5rem; border: 0; border-radius: 6px;
      background: var(--accent); color: #fff; font: inherit; font-weight: 700;
      letter-spacing: 0.4px; text-transform: uppercase; cursor: pointer;
    }
    .submit-btn:hover { background: #0a63c4; }
    .submit-btn:disabled { opacity: 0.65; cursor: not-allowed; }

    .status { display: none; margin-top: 0.9rem; padding: 0.7rem 0.9rem; border-radius: 10px; font-size: 0.92rem; word-break: break-word; }
    .status.ok { display: block; background: #dcfce7; color: #166534; }
    .status.err { display: block; background: #fee2e2; color: #991b1b; }

    .map-tag { display: inline-block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--accent); margin-bottom: 0.4rem; }
    #officeMap { height: 300px; width: 100%; border-radius: 12px; border: 1px solid var(--tint); position: relative; z-index: 1; margin: 1rem 0; }
    .map-note { color: var(--muted); font-size: 0.88rem; margin: 0; }

    @media (max-width: 900px) {
      .info-grid { grid-template-columns: repeat(2, 1fr); margin-top: -2.5rem; }
      .contact-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
      .info-grid { grid-template-columns: 1fr; }
      .form-row { grid-template-columns: 1fr; gap: 0; }
      .form-card, .map-panel { padding: 1.2rem; }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">
        <img class="logo-img" src="../images/wum-logo.jpg" alt="Women University Multan logo">
        <span>CampusTransit WUM</span>
      </a>

      <button class="nav-toggle" id="navToggle" type="button" aria-expanded="false" aria-controls="siteNav">Menu</button>

      <nav class="main-nav" id="siteNav" aria-label="Main navigation">
        
       <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="schedule.php">Bus Tracking</a></li>
        <li><a href="analytics.php">Reports</a></li>
        <li><a class="active" href="contact.php" aria-current="page">Contact Us</a></li>
        <li><a href="../login.php">Admin Login</a></li>
        <li><a href="../driver_login.php">Driver Login</a></li>
      </ul>
</nav>
    </div>
  </header>

  <main>

    <section class="page-hero">
      <div class="container">
        <div class="page-eyebrow">Support for every journey</div>
        <h1>Contact University Transport Support</h1>
        <p>Have a question about routes, timings or transport services? Send a message and the team will guide you.</p>
      </div>
    </section>

    <div class="container info-grid">
      <div class="info-card">
        <div class="info-ico" aria-hidden="true">📍</div>
        <div>
          <p class="info-label">Transport Office</p>
          <p class="info-value">Women University Multan, Punjab</p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-ico" aria-hidden="true">📞</div>
        <div>
          <p class="info-label">Phone Number</p>
          <p class="info-value"><a href="tel:03002938175">0300-2938175</a></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-ico" aria-hidden="true">✉️</div>
        <div>
          <p class="info-label">Email Address</p>
          <p class="info-value"><a href="mailto:transport@wum.edu.pk">transport@wum.edu.pk</a></p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-ico" aria-hidden="true">🕒</div>
        <div>
          <p class="info-label">Office Hours</p>
          <p class="info-value">Mon - Sat, 7:00 AM - 5:00 PM</p>
        </div>
      </div>
    </div>

    <section class="contact-section">
      <div class="container contact-grid">

        <div class="form-card">
          <h2>Send us a message</h2>
          <p class="form-sub">We will help point you in the right direction.</p>

          <form id="contactForm">
            <div class="hp" aria-hidden="true">
               <label for="hpField">Leave this field empty</label>
            <input type="text" id="hpField" name="hp_field" tabindex="-1" autocomplete="off" data-lpignore="true">
            </div>
            <div class="form-row">
              <div class="field">
                <label for="cName">Name *</label>
                <input type="text" id="cName" name="name" minlength="2" maxlength="100" required placeholder="Your full name">
              </div>
              <div class="field">
                <label for="cEmail">Email *</label>
                <input type="email" id="cEmail" name="email" maxlength="150" required placeholder="you@example.com">
              </div>
            </div>

            <div class="form-row">
              <div class="field">
                <label for="cPhone">Phone (optional)</label>
                <input type="tel" id="cPhone" name="phone" maxlength="20" pattern="[0-9+\- ]{7,20}" placeholder="0300-0000000">
              </div>
              <div class="field">
                <label for="cSubject">Subject *</label>
                <select id="cSubject" name="subject" required>
                  <option value="" disabled selected>What can we help with?</option>
                  <option value="Bus pass">Bus pass</option>
                  <option value="Routes and timings">Routes and timings</option>
                  <option value="Complaint or feedback">Complaint or feedback</option>
                  <option value="Other">Other</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label for="cMessage">Message *</label>
              <textarea id="cMessage" name="message" minlength="5" maxlength="1000" required placeholder="Write your message here..."></textarea>
              <span class="count" id="cCount">0 / 1000</span>
            </div>

            <button type="submit" class="submit-btn" id="cSend">Send Message</button>
            <div class="status" id="contactStatus" role="status" aria-live="polite"></div>
          </form>
        </div>

        <div class="map-panel">
          <span class="map-tag">Campus location</span>
          <h2>Find the Transport Office</h2>
          <div id="officeMap" role="region" aria-label="Transport office location"></div>
          <p class="map-note">The map position is approximate. Confirm the official office location with the university before publishing.</p>
        </div>

      </div>
    </section>

  </main>

  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div>
          <h4>CampusTransit WUM</h4>
          <p>A student-built transport planning prototype for Women University Multan.</p>
        </div>
        <div>
          <h4>Quick Links</h4>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="schedule.php">Bus Tracking</a></li>
            <li><a href="analytics.php">Reports</a></li>
            <li><a href="contact.php">Contact Us</a></li>
          </ul>
        </div>
        <div>
          <h4>Contact</h4>
          <ul>
            <li>Address: Women University Multan, Punjab, Pakistan</li>
            <li>Helpline: <a href="tel:03002938175">0300-2938175</a></li>
            <li>Email: <a href="mailto:transport@wum.edu.pk">transport@wum.edu.pk</a></li>
          </ul>
        </div>
        <div>
          <span class="edu-badge">
            <strong>Educational Project</strong>
            This website is an educational project for learning purposes. It is not an official Women University Multan service.
          </span>
        </div>
      </div>
      <div class="footer-bottom">© 2026 CampusTransit WUM. Educational project prototype.</div>
    </div>
  </footer>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    (function () {
      "use strict";

      var navToggle = document.getElementById("navToggle");
      var siteNav = document.getElementById("siteNav");
      navToggle.addEventListener("click", function () {
        var isOpen = siteNav.classList.toggle("open");
        navToggle.setAttribute("aria-expanded", String(isOpen));
        navToggle.textContent = isOpen ? "Close" : "Menu";
      });

      var OFFICE = [30.1978, 71.4694];
      var mapEl = document.getElementById("officeMap");
      if (typeof L !== "undefined") {
        var map = L.map("officeMap").setView(OFFICE, 14);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
          attribution: "&copy; OpenStreetMap contributors",
          maxZoom: 19
        }).addTo(map);
        L.circleMarker(OFFICE, {
          radius: 10, color: "#ffffff", weight: 3, fillColor: "#1e7be0", fillOpacity: 1
        }).addTo(map).bindTooltip("Transport Office (approximate)", { permanent: true, direction: "top" });
      } else {
        mapEl.textContent = "The map could not load. Check your internet connection and refresh the page.";
      }

      var form = document.getElementById("contactForm");
      var sendBtn = document.getElementById("cSend");
      var statusEl = document.getElementById("contactStatus");
      var msgEl = document.getElementById("cMessage");
      var countEl = document.getElementById("cCount");

      msgEl.addEventListener("input", function () {
        countEl.textContent = msgEl.value.length + " / 1000";
      });

      function showStatus(kind, text) {
        statusEl.className = "status " + kind;
        statusEl.textContent = text;
      }

      form.addEventListener("submit", function (event) {
        event.preventDefault();
        statusEl.className = "status";
        statusEl.textContent = "";
        sendBtn.disabled = true;
        sendBtn.textContent = "Sending...";

        fetch("../contact_submit.php", { method: "POST", body: new FormData(form) })
          .then(function (res) {
            return res.text().then(function (text) {
              var data;
              try {
                data = JSON.parse(text);
              } catch (e) {
                throw new Error("Server ka jawab: " + text.slice(0, 200));
              }
              return { ok: res.ok, data: data };
            });
          })
          .then(function (result) {
            if (result.ok && result.data.ok) {
              form.reset();
              countEl.textContent = "0 / 1000";
              showStatus("ok", "Thank you! Your message has been sent. We will get back to you soon.");
            } else {
              showStatus("err", (result.data && result.data.error) || "Something went wrong. Please try again.");
            }
          })
          .catch(function (err) {
            showStatus("err", err.message);
          })
          .then(function () {
            sendBtn.disabled = false;
            sendBtn.textContent = "Send Message";
          });
      });
    })();
  </script>

</body>
</html>