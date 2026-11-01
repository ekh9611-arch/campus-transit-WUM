<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function stat_text($value): string
{
    return $value === null ? '—' : (string)$value;
}

$stats = [
    'total_buses'  => null,
    'active_buses' => null,
    'total_routes' => null,
    'total_stops'  => null,
];

try {
    $db = new mysqli('localhost', 'root', '', 'university_transport');
    $db->set_charset('utf8mb4');

    $stmt = $db->prepare(
        "SELECT (SELECT COUNT(*) FROM buses)                          AS total_buses,
                (SELECT COUNT(*) FROM buses WHERE status = 'active')  AS active_buses,
                (SELECT COUNT(*) FROM routes)                         AS total_routes,
                (SELECT COUNT(*) FROM route_stops)                    AS total_stops"
    );
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $db->close();

    if ($row) {
        foreach ($stats as $key => $unused) {
            $stats[$key] = (int)$row[$key];
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log('Home page DB error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="CampusTransit WUM: a student project prototype for university transport planning at Women University Multan.">
  <title>CampusTransit WUM | Home</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .live-stats { padding: 2.5rem 0 0; }
    .live-stats-head { display: flex; flex-wrap: wrap; align-items: center; gap: 0.6rem; margin-bottom: 1rem; }
    .live-stats-head h2 { margin: 0; }
    .live-stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
    .live-stat { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); border-top: 4px solid var(--accent); padding: 1.1rem; text-align: center; }
    .live-stat .num { font-size: 1.9rem; font-weight: 800; color: var(--primary); margin: 0; }
    .live-stat .lbl { font-size: 0.85rem; color: var(--muted); margin: 0; }
    .live-stats-link { margin-top: 1rem; font-weight: 700; }
    @media (max-width: 900px) { .live-stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 480px) { .live-stats-grid { grid-template-columns: 1fr; } }

    .alert-banner { background: #dc2626; color: #fff; padding: 14px 20px; font-weight: 700; font-size: 0.95rem; line-height: 1.5; text-align: center; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3); }
    .alert-banner[hidden] { display: none; }
    .route-pick { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px 16px; background: #eaf4fd; font-size: 0.9rem; }
    .route-pick select { padding: 6px 10px; border: 1px solid #dbeafe; border-radius: 6px; font: inherit; }
    .notify-btn { padding: 6px 12px; border: none; border-radius: 6px; background: #1e7be0; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
    .notify-btn:disabled { opacity: 0.6; cursor: default; }
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
        <li><a class="active" href="index.php" aria-current="page">Home</a></li>
        <li><a href="schedule.php">Bus Tracking</a></li>
        <li><a href="analytics.php">Reports</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="../login.php">Admin Login</a></li>
        <li><a href="../driver_login.php">Driver Login</a></li>
      </ul>
    </nav>
  </div>
</header>

<div id="alertBanner" class="alert-banner" role="alert" hidden></div>
<div class="route-pick">
  <label for="routePick">Your route:</label>
  <select id="routePick">
    <option value="0">All routes</option>
  </select>
  <button id="notifyBtn" class="notify-btn" type="button">🔔 Enable alerts</button>
  <button id="checkAlertsBtn" class="notify-btn" type="button">🚨 Check alerts</button>
</div>

<main>

  <section class="hero" aria-label="Introduction">
    <div class="container hero-inner">
      <div class="hero-content">
        <h1>CampusTransit WUM</h1>
        <p class="hero-slogan">Transport everywhere: safe, punctual and reachable campus travel for every student.</p>
        <p class="hero-meta">
          <span class="badge">Live Data</span>
          <span>Fleet figures come from the transport database.</span>
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="schedule.php">Track Your Bus</a>
        </div>
      </div>
    </div>
  </section>

  <section class="feature-section" aria-label="Key features">
    <div class="container">
      <div class="feature-row">
        <article class="feature">
          <div class="feature-icon" aria-hidden="true">🛡️</div>
          <div>
            <h3>Safe Rides</h3>
            <p>Vetted drivers and routine vehicle checks before every trip.</p>
          </div>
        </article>
        <article class="feature">
          <div class="feature-icon" aria-hidden="true">🗺️</div>
          <div>
            <h3>Campus Routes</h3>
            <p>Loops linking the Mattital, Kutchery, Kumharanwala and Bosan Road points.</p>
          </div>
        </article>
        <article class="feature">
          <div class="feature-icon" aria-hidden="true">🎓</div>
          <div>
            <h3>For Students</h3>
            <p>Live bus tracking, timings and alerts for every enrolled student.</p>
          </div>
        </article>
        <article class="feature">
          <div class="feature-icon" aria-hidden="true">⏱️</div>
          <div>
            <h3>Reliable &amp; On-Time</h3>
            <p>Scheduled trips are monitored, with backup routes ready if a bus is delayed.</p>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section class="live-stats" aria-labelledby="liveTitle">
    <div class="container">
      <div class="live-stats-head">
        <h2 id="liveTitle">Live Fleet Snapshot</h2>
        <span class="badge">Live Data</span>
      </div>

      <div class="live-stats-grid">
        <div class="live-stat">
          <p class="num"><?= h(stat_text($stats['total_buses'])) ?></p>
          <p class="lbl">Total Buses</p>
        </div>
        <div class="live-stat">
          <p class="num"><?= h(stat_text($stats['active_buses'])) ?></p>
          <p class="lbl">Buses On Route</p>
        </div>
        <div class="live-stat">
          <p class="num"><?= h(stat_text($stats['total_routes'])) ?></p>
          <p class="lbl">Routes</p>
        </div>
        <div class="live-stat">
          <p class="num"><?= h(stat_text($stats['total_stops'])) ?></p>
          <p class="lbl">Stops Covered</p>
        </div>
      </div>

      <p class="live-stats-link"><a href="schedule.php">See live bus locations on the map &rarr;</a></p>
    </div>
  </section>

  <section class="about" aria-labelledby="aboutTitle">
    <div class="container about-grid">
      <figure class="vehicle-visual">
        <img src="../images/bus.jpg" alt="CampusTransit WUM bus on a campus route">
      </figure>

      <div>
        <div class="about-heading">
          <h2 id="aboutTitle">Built around your daily campus commute</h2>
          <span class="badge">Sample/Demo Data</span>
        </div>
        <p>
          CampusTransit WUM is a student project that explores how university transport can make
          daily life easier. The focus is on your comfort, predictable timetables, safe travel
          routes and reaching every campus point without stress.
        </p>
        <p>
          Each route is planned around class schedules so students can plan their day with
          confidence, whether they travel from the city or nearby areas.
        </p>

        <ul class="checks">
          <li>Backup routes ready when a bus breaks down, so classes are not missed</li>
          <li>Load support solutions that redistribute students across buses during peak hours</li>
          <li>Predictable timelines published in advance for each campus loop</li>
          <li>Safe, monitored travel with clear contact points for every trip</li>
        </ul>
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
          <li><a href="../login.php">Admin Login</a></li>
          <li><a href="../driver_login.php">Driver Login</a></li>
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

<script>
(function () {
  "use strict";

  // Mobile menu
  var navToggle = document.getElementById("navToggle");
  var siteNav = document.getElementById("siteNav");
  navToggle.addEventListener("click", function () {
    var isOpen = siteNav.classList.toggle("open");
    navToggle.setAttribute("aria-expanded", String(isOpen));
    navToggle.textContent = isOpen ? "Close" : "Menu";
  });

  // Breakdown alerts
  var box = document.getElementById("alertBanner");
  var pick = document.getElementById("routePick");
  var checkBtn = document.getElementById("checkAlertsBtn");
  var notifyBtn = document.getElementById("notifyBtn");
  if (!box || !pick) return;

  var KEY = "utms_route";
  var routesLoaded = false;

  function fillRoutes(data) {
    if (routesLoaded || !Array.isArray(data.routes)) return;
    data.routes.forEach(function (no) {
      var opt = document.createElement("option");
      opt.value = String(no);
      opt.textContent = "Route " + no;
      pick.appendChild(opt);
    });
    var saved = localStorage.getItem(KEY);
    if (saved !== null && pick.querySelector('option[value="' + saved + '"]')) {
      pick.value = saved;
    }
    routesLoaded = true;
  }

  function render(list, showEmpty) {
    box.textContent = "";
    box.style.background = "";

    if (list.length === 0) {
      if (showEmpty) {
        box.style.background = "#16a34a";
        box.textContent = "Is route par abhi koi breakdown alert nahi hai.";
        box.hidden = false;
      } else {
        box.hidden = true;
      }
      return;
    }

    box.textContent = list.map(function (a) {
      return "Bus " + a.bus_number +
        (a.route_no ? " (Route " + a.route_no + ")" : "") +
        " is broken down. Please wait for another bus.";
    }).join(" ");
    box.hidden = false;
  }

  function check(showEmpty, notify) {
    fetch("../get_alerts.php?route=" + encodeURIComponent(pick.value), { cache: "no-store" })
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status);
        return res.json();
      })
      .then(function (data) {
        fillRoutes(data);
        var list = data.alerts || [];
        render(list, showEmpty);

        if (notify && list.length > 0 && "Notification" in window && Notification.permission === "granted") {
          new Notification("CampusTransit WUM", {
            body: list.map(function (a) { return "Bus " + a.bus_number + " is broken down."; }).join(" ")
          });
        }
      })
      .catch(function () {
        box.hidden = true;
        box.textContent = "";
      });
  }

  pick.addEventListener("change", function () {
    localStorage.setItem(KEY, pick.value);
    check(false, false);
  });

  if (checkBtn) {
    checkBtn.addEventListener("click", function () {
      check(true, true);
    });
  }

  if (notifyBtn) {
    if (!("Notification" in window)) {
      notifyBtn.hidden = true;
    } else if (Notification.permission === "granted") {
      notifyBtn.textContent = "🔔 Alerts on";
      notifyBtn.disabled = true;
    }
    notifyBtn.addEventListener("click", function () {
      Notification.requestPermission().then(function (permission) {
        notifyBtn.textContent = permission === "granted" ? "🔔 Alerts on" : "Alerts blocked";
        notifyBtn.disabled = true;
      });
    });
  }

  // Page khulte hi sirf route list bharein, banner nahi
  fetch("../get_alerts.php?route=0", { cache: "no-store" })
    .then(function (res) { return res.json(); })
    .then(function (data) { fillRoutes(data); })
    .catch(function () {});
})();
</script>

</body>
</html>