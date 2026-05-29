function resolveApiBase() {
  if (window.location.protocol === "file:") {
    return "http://localhost/Ticket-System/api";
  }

  const path = window.location.pathname.endsWith("/")
    ? window.location.pathname
    : window.location.pathname.replace(/\/[^/]*$/, "/");

  return `${window.location.origin}${path}api`;
}

const API_BASE = resolveApiBase();
const pageCopy = {
  dashboard: {
    title: "Dashboard",
    subtitle: "Working prototype connected to the PHP/MySQL backend."
  },
  tickets: {
    title: "Tickets",
    subtitle: "View details, assign agents, escalate, reassign, and resolve tickets."
  },
  queues: {
    title: "Queues",
    subtitle: "Queue numbers are grouped by priority and ordered by arrival time."
  },
  sla: {
    title: "SLA Monitor",
    subtitle: "Track At Risk and Over SLA tickets."
  },
  rules: {
    title: "Escalation Rules",
    subtitle: "Priority-based backend triggers for routing and escalation."
  },
  agents: {
    title: "Agents",
    subtitle: "Support roster and current workload."
  }
};

let tickets = [];
let agents = [];
let dashboard = null;
let queues = {};
let slaMonitor = { over_sla: [], at_risk: [] };
let rules = [];
let selectedTicketId = null;
let activePriorityFilter = "all";
let apiOnline = false;

const navItems = document.querySelectorAll("[data-view-target]");
const views = document.querySelectorAll(".view");
const filterChips = document.querySelectorAll("[data-priority-filter]");
const ticketTable = document.querySelector("#ticketTable");
const searchInput = document.querySelector("#ticketSearch");
const statusFilter = document.querySelector("#statusFilter");
const modal = document.querySelector("#ticketModal");
const form = document.querySelector("#ticketForm");
const assignAgentSelect = document.querySelector("#assignAgentSelect");
const createAgentSelect = document.querySelector("#createAgentSelect");
const toast = document.querySelector("#toast");

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => {
    const entities = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    };
    return entities[character];
  });
}

async function api(path, options = {}) {
  const response = await fetch(`${API_BASE}/${path.replace(/^\//, "")}`, {
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.headers || {})
    },
    ...options
  });

  const text = await response.text();
  let payload = {};
  try {
    payload = text ? JSON.parse(text) : {};
  } catch {
    throw new Error("The backend returned invalid JSON. Check Apache/PHP in XAMPP.");
  }

  if (!response.ok) {
    throw new Error(payload.message || payload.error || `Request failed (${response.status})`);
  }

  return payload;
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.remove("hidden");
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.add("hidden"), 2600);
}

function setConnection(online, message = "") {
  apiOnline = online;
  const status = document.querySelector("#connectionStatus");
  status.classList.toggle("offline", !online);
  document.querySelector("#connectionText").textContent = online ? "Backend connected" : "Backend offline";

  const banner = document.querySelector("#apiBanner");
  if (online) {
    banner.classList.add("hidden");
    banner.textContent = "";
  } else {
    banner.textContent =
      message ||
      "Backend is not reachable. Start Apache and MySQL in XAMPP, then import backend/database/schema.sql and seed.sql.";
    banner.classList.remove("hidden");
  }
}

function priorityClass(priority) {
  return String(priority || "").toLowerCase();
}

function slaClass(sla) {
  return String(sla?.status || "").replace(/-/g, "_");
}

function slaBadge(sla) {
  return `<span class="sla-label ${slaClass(sla)}">${escapeHtml(sla?.label || "-")}</span>`;
}

function selectedTicket() {
  return tickets.find((ticket) => ticket.id === selectedTicketId) || tickets[0] || null;
}

function showView(viewName) {
  const safeView = pageCopy[viewName] ? viewName : "dashboard";

  views.forEach((view) => view.classList.toggle("active", view.dataset.view === safeView));
  navItems.forEach((item) => item.classList.toggle("active", item.dataset.viewTarget === safeView));

  document.querySelector("#pageTitle").textContent = pageCopy[safeView].title;
  document.querySelector("#pageSubtitle").textContent = pageCopy[safeView].subtitle;
  window.location.hash = safeView;
}

function getFilteredTickets() {
  const query = searchInput.value.trim().toLowerCase();
  const status = statusFilter.value;

  return tickets.filter((ticket) => {
    const matchesText = [
      ticket.id,
      ticket.title,
      ticket.requester_name,
      ticket.requester_email,
      ticket.category,
      ticket.priority,
      ticket.agent_name
    ]
      .join(" ")
      .toLowerCase()
      .includes(query);

    const matchesPriority = activePriorityFilter === "all" || ticket.priority === activePriorityFilter;
    const matchesStatus = status === "all" || ticket.status === status;

    return matchesText && matchesPriority && matchesStatus;
  });
}

function renderTable() {
  const rows = getFilteredTickets();
  if (!rows.length) {
    ticketTable.innerHTML = `<tr><td class="empty-state" colspan="7">No tickets match the current filters.</td></tr>`;
    return;
  }

  ticketTable.innerHTML = rows
    .map((ticket) => {
      const queue = ticket.queue_position ? `#${ticket.queue_position}` : "-";
      return `
        <tr data-ticket-id="${escapeHtml(ticket.id)}" class="${ticket.id === selectedTicketId ? "selected" : ""}">
          <td>
            <div class="ticket-title">
              <strong>${escapeHtml(ticket.title)}</strong>
              <span>${escapeHtml(ticket.id)} / ${escapeHtml(ticket.category)}</span>
            </div>
          </td>
          <td>${escapeHtml(ticket.requester_name)}</td>
          <td>${escapeHtml(ticket.agent_name)}</td>
          <td><span class="pill queue">${escapeHtml(queue)}</span></td>
          <td><span class="pill ${priorityClass(ticket.priority)}">${escapeHtml(ticket.priority)}</span></td>
          <td><span class="pill status">${escapeHtml(ticket.status)}</span></td>
          <td>${slaBadge(ticket.sla)}<br><small>${escapeHtml(ticket.sla?.display || "")}</small></td>
        </tr>
      `;
    })
    .join("");

  ticketTable.querySelectorAll("tr[data-ticket-id]").forEach((row) => {
    row.addEventListener("click", () => {
      selectedTicketId = row.dataset.ticketId;
      renderAll();
    });
  });
}

function renderDetails() {
  const ticket = selectedTicket();
  if (!ticket) {
    document.querySelector("#detailTitle").textContent = "No ticket selected";
    return;
  }

  selectedTicketId = ticket.id;
  document.querySelector("#detailTitle").textContent = ticket.title;
  document.querySelector("#detailId").textContent = ticket.id;
  document.querySelector("#detailRequester").textContent = ticket.requester_name;
  document.querySelector("#detailEmail").textContent = ticket.requester_email || "-";
  document.querySelector("#detailAgent").textContent = `${ticket.agent_name}${ticket.agent_role ? ` (${ticket.agent_role})` : ""}`;
  document.querySelector("#detailPriority").innerHTML =
    `<span class="pill ${priorityClass(ticket.priority)}">${escapeHtml(ticket.priority)}</span>`;
  document.querySelector("#detailQueue").textContent = ticket.queue_position
    ? `#${ticket.queue_position} in ${ticket.priority} queue`
    : "Not in active queue";
  document.querySelector("#detailSla").innerHTML =
    `${slaBadge(ticket.sla)} ${escapeHtml(ticket.sla?.display || "")}`;
  document.querySelector("#detailEscalation").textContent = `Level ${ticket.escalation_level}`;
  document.querySelector("#detailDescription").textContent = ticket.description || "No description provided.";

  if (ticket.agent_id && assignAgentSelect.options.length) {
    assignAgentSelect.value = String(ticket.agent_id);
  }

  const matching = ticket.matching_rules || [];
  document.querySelector("#matchingRules").innerHTML = matching.length
    ? matching.map((rule) => `<li>${escapeHtml(rule.rule_name)}</li>`).join("")
    : `<li>No trigger currently applies.</li>`;

  const events = ticket.events || [];
  document.querySelector("#activityLog").innerHTML = events.length
    ? events.map((event) => `<li>${escapeHtml(event.message)}</li>`).join("")
    : `<li>No activity recorded.</li>`;
}

function renderMetrics() {
  if (!dashboard) {
    return;
  }

  document.querySelector("#openTickets").textContent = dashboard.open_tickets ?? 0;
  document.querySelector("#slaRisk").textContent = dashboard.at_risk_sla ?? 0;
  document.querySelector("#overSla").textContent = dashboard.over_sla ?? 0;
  document.querySelector("#resolvedToday").textContent = dashboard.resolved_today ?? 0;
}

function ticketCard(ticket) {
  return `
    <article class="watch-card">
      <header>
        <h4>${escapeHtml(ticket.id)}</h4>
        <span class="pill ${priorityClass(ticket.priority)}">${escapeHtml(ticket.priority)}</span>
      </header>
      <p>${escapeHtml(ticket.title)}</p>
      <p>${escapeHtml(ticket.agent_name)} · ${escapeHtml(ticket.sla?.label)} · ${escapeHtml(ticket.sla?.display)}</p>
    </article>
  `;
}

function renderDashboard() {
  if (!dashboard) {
    return;
  }

  document.querySelector("#watchlist").innerHTML = (dashboard.watchlist || []).length
    ? dashboard.watchlist.map(ticketCard).join("")
    : `<p class="empty-state">No priority alerts right now.</p>`;

  document.querySelector("#prioritySnapshot").innerHTML = (dashboard.priority_snapshot || [])
    .map(
      (item) => `
        <article class="priority-card ${priorityClass(item.priority)}">
          <header>
            <h4>${escapeHtml(item.priority)}</h4>
            <strong>${item.count}</strong>
          </header>
          <p>${item.count === 1 ? "1 active ticket" : `${item.count} active tickets`}</p>
        </article>
      `
    )
    .join("");

  document.querySelector("#dashboardTimeline").innerHTML = (dashboard.recent_activity || []).length
    ? dashboard.recent_activity.map((activity) => `<li>${escapeHtml(activity)}</li>`).join("")
    : `<li>No recent activity.</li>`;
}

function renderQueues() {
  const priorities = ["Urgent", "High", "Medium", "Low"];
  document.querySelector("#priorityQueueCards").innerHTML = priorities
    .map((priority) => {
      const queue = queues[priority] || { count: 0, tickets: [] };
      const next = queue.tickets?.[0];
      return `
        <article class="priority-card ${priorityClass(priority)}">
          <header>
            <h4>${priority} Queue</h4>
            <strong>${queue.count || 0}</strong>
          </header>
          <p>${next ? `Next: ${escapeHtml(next.id)} (#${next.queue_position})` : "Queue is clear"}</p>
        </article>
      `;
    })
    .join("");

  const rows = priorities.flatMap((priority) => queues[priority]?.tickets || []);
  document.querySelector("#queueTableBody").innerHTML = rows.length
    ? rows
        .map(
          (ticket) => `
        <tr>
          <td><span class="pill ${priorityClass(ticket.priority)}">${escapeHtml(ticket.priority)}</span></td>
          <td><span class="pill queue">#${escapeHtml(ticket.queue_position)}</span></td>
          <td>${escapeHtml(ticket.id)}</td>
          <td>${escapeHtml(ticket.title)}</td>
          <td>${escapeHtml(ticket.agent_name)}</td>
          <td>${slaBadge(ticket.sla)} ${escapeHtml(ticket.sla?.display || "")}</td>
        </tr>
      `
        )
        .join("")
    : `<tr><td class="empty-state" colspan="6">All queues are clear.</td></tr>`;
}

function renderSlaMonitor() {
  document.querySelector("#overSlaList").innerHTML = (slaMonitor.over_sla || []).length
    ? slaMonitor.over_sla.map(ticketCard).join("")
    : `<p class="empty-state">No Over SLA tickets.</p>`;

  document.querySelector("#atRiskList").innerHTML = (slaMonitor.at_risk || []).length
    ? slaMonitor.at_risk.map(ticketCard).join("")
    : `<p class="empty-state">No At Risk tickets.</p>`;
}

function renderAgents() {
  const options = agents
    .map((agent) => `<option value="${agent.id}">${escapeHtml(agent.name)} - ${escapeHtml(agent.role)}</option>`)
    .join("");
  assignAgentSelect.innerHTML = options;
  createAgentSelect.innerHTML = `<option value="">Auto assign</option>${options}`;

  const max = Math.max(...agents.map((agent) => Number(agent.active_tickets || 0)), 1);
  document.querySelector("#workloadBars").innerHTML = agents
    .map(
      (agent) => `
        <div class="bar-row">
          <div class="bar-meta">
            <span>${escapeHtml(agent.name)}</span>
            <span>${agent.active_tickets} active</span>
          </div>
          <div class="bar-track" aria-hidden="true">
            <div class="bar-fill" style="width: ${(Number(agent.active_tickets || 0) / max) * 100}%"></div>
          </div>
        </div>
      `
    )
    .join("");

  document.querySelector("#agentGrid").innerHTML = agents
    .map(
      (agent) => `
        <article class="roster-card">
          <header>
            <h4>${escapeHtml(agent.name)}</h4>
            <span class="pill status">${agent.active_tickets} active</span>
          </header>
          <p>${escapeHtml(agent.role)}</p>
          <p>${escapeHtml(agent.email)}</p>
        </article>
      `
    )
    .join("");
}

function renderRules() {
  document.querySelector("#rulesTableBody").innerHTML = rules.length
    ? rules
        .map(
          (rule) => `
        <tr>
          <td>${escapeHtml(rule.rule_name)}</td>
          <td>${escapeHtml(rule.condition_text)}</td>
          <td>${escapeHtml(rule.action_text)}</td>
          <td>${escapeHtml(rule.owner)}</td>
          <td>${Number(rule.target_minutes) > 0 ? `${rule.target_minutes} min` : "Immediate"}</td>
        </tr>
      `
        )
        .join("")
    : `<tr><td class="empty-state" colspan="5">No escalation rules found.</td></tr>`;
}

function renderAll() {
  renderAgents();
  renderTable();
  renderDetails();
  renderMetrics();
  renderDashboard();
  renderQueues();
  renderSlaMonitor();
  renderRules();
}

async function loadData() {
  const [ticketPayload, dashboardPayload, queuePayload, slaPayload, agentPayload, rulesPayload] = await Promise.all([
    api("tickets"),
    api("dashboard"),
    api("queues"),
    api("sla"),
    api("agents"),
    api("rules")
  ]);

  tickets = ticketPayload.tickets || [];
  dashboard = dashboardPayload;
  queues = queuePayload.queues || {};
  slaMonitor = slaPayload || { over_sla: [], at_risk: [] };
  agents = agentPayload.agents || [];
  rules = rulesPayload.rules || [];

  if (!selectedTicketId && tickets.length) {
    selectedTicketId = tickets[0].id;
  } else if (selectedTicketId && !tickets.some((ticket) => ticket.id === selectedTicketId)) {
    selectedTicketId = tickets[0]?.id || null;
  }

  setConnection(true);
  renderAll();
}

async function runTicketAction(action, body = null) {
  const ticket = selectedTicket();
  if (!ticket) {
    return;
  }

  await api(`tickets/${encodeURIComponent(ticket.id)}/${action}`, {
    method: "POST",
    ...(body ? { body: JSON.stringify(body) } : {})
  });
  await loadData();
}

navItems.forEach((item) => {
  item.addEventListener("click", () => showView(item.dataset.viewTarget));
});

window.addEventListener("hashchange", () => {
  showView(window.location.hash.replace("#", ""));
});

filterChips.forEach((chip) => {
  chip.addEventListener("click", () => {
    filterChips.forEach((item) => item.classList.remove("active"));
    chip.classList.add("active");
    activePriorityFilter = chip.dataset.priorityFilter;
    renderTable();
  });
});

searchInput.addEventListener("input", renderTable);
statusFilter.addEventListener("change", renderTable);

document.querySelector("#refreshData").addEventListener("click", () => {
  loadData().catch((error) => setConnection(false, error.message));
});

document.querySelector("#openTicketModal").addEventListener("click", () => {
  if (!apiOnline) {
    setConnection(false, "Backend is offline. Start XAMPP first.");
    return;
  }
  modal.showModal();
});

document.querySelector("#closeTicketModal").addEventListener("click", () => modal.close());
document.querySelector("#cancelTicketModal").addEventListener("click", () => modal.close());

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = new FormData(form);
  const body = Object.fromEntries(data.entries());
  if (body.agent_id) {
    body.agent_id = Number(body.agent_id);
  }

  try {
    const payload = await api("tickets", {
      method: "POST",
      body: JSON.stringify(body)
    });
    selectedTicketId = payload.ticket.id;
    form.reset();
    modal.close();
    showView("tickets");
    await loadData();
    showToast(`Created ${payload.ticket.id}`);
  } catch (error) {
    setConnection(false, error.message);
  }
});

document.querySelector("#assignAgentBtn").addEventListener("click", async () => {
  try {
    await runTicketAction("assign", { agent_id: Number(assignAgentSelect.value) });
    showToast("Assignment saved");
  } catch (error) {
    setConnection(false, error.message);
  }
});

document.querySelector("#escalateTicket").addEventListener("click", () => {
  runTicketAction("escalate")
    .then(() => showToast("Ticket escalated"))
    .catch((error) => setConnection(false, error.message));
});

document.querySelector("#reassignTicket").addEventListener("click", () => {
  runTicketAction("reassign")
    .then(() => showToast("Ticket reassigned"))
    .catch((error) => setConnection(false, error.message));
});

document.querySelector("#resolveTicket").addEventListener("click", () => {
  runTicketAction("resolve")
    .then(() => showToast("Ticket resolved"))
    .catch((error) => setConnection(false, error.message));
});

showView(window.location.hash.replace("#", "") || "dashboard");
loadData().catch((error) => setConnection(false, error.message));
