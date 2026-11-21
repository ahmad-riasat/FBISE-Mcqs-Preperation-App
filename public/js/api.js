let csrfToken = "";

class ApiError extends Error {
  constructor(message, status, fields) {
    super(message);
    this.status = status;
    this.fields = fields || {};
  }
}

async function apiRequest(path, options = {}) {
  const method = options.method || "GET";
  const config = { method: method, headers: {}, credentials: "same-origin" };

  if (method !== "GET") {
    config.headers["Content-Type"] = "application/json";
    config.headers["X-CSRF-Token"] = csrfToken;
    config.body = JSON.stringify(options.body || {});
  }

  let response;
  try {
    response = await fetch("api/" + path, config);
  } catch (networkError) {
    throw new ApiError("Cannot reach the server. Check your connection and try again.", 0);
  }

  let data;
  try {
    data = await response.json();
  } catch (parseError) {
    throw new ApiError("The server sent an unexpected reply.", response.status);
  }

  if (data.csrf_token) {
    csrfToken = data.csrf_token;           // always keep the newest token
  }
  if (!response.ok || !data.ok) {
    throw new ApiError(data.error || "Request failed.", response.status, data.fields);
  }
  return data;
}