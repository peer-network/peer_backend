export default {
  async fetch(request) {
    try {
      const payload = await request.json();
      const action = payload.action;
      const pr = payload.pull_request;

      // Only forward Dependabot PR events
      if (
        pr?.user?.login === "dependabot[bot]" &&
        ["opened", "reopened", "synchronize"].includes(action)
      ) {
        const message =
          `:package: **Dependabot opened/updated a PR**\n` +
          `**Title:** ${pr.title}\n` +
          `**Link:** ${pr.html_url}\n` +
          `**Branch:** ${pr.head.ref}\n` +
          `Opened by: Dependabot`;

        // Post to Discord
        await fetch(DISCORD_WEBHOOK_URL, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ content: message }),
        });
      }

      return new Response("ok", { status: 200 });
    } catch (err) {
      return new Response("error: " + err.message, { status: 500 });
    }
  },
};
