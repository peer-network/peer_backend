const fs = require("fs");
const path = "/etc/newman";

let collections = fs.readdirSync(path).filter(file =>
  file.endsWith("_graphql_postman_collection.json")
);

collections.sort((a, b) => {
  const numA = parseInt(a.split("_")[0], 10);
  const numB = parseInt(b.split("_")[0], 10);
  return numA - numB;
});

let merged = {
  info: {
    name: "Merged Collection",
    schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  item: [],
  event: [
    {
        "listen": "test",
        "script": {
            "type": "text/javascript",
            "packages": {},
            "requests": {},
            "exec": [
                "// === Global Internal Server Error Detector ===",
                "const body = pm.response.json();",
                "",
                "if (body.errors && Array.isArray(body.errors) && body.errors.length > 0) {",
                "    const internalErrors = body.errors.filter(e => ",
                "        (e.message && e.message.toLowerCase().includes(\"internal server error\")) ||",
                "        (e.extensions && e.extensions.debugMessage && e.extensions.debugMessage.toLowerCase().includes(\"internal\"))",
                "    );",
                "",
                "    if (internalErrors.length > 0) {",
                "        pm.test(\"Internal Server Error SHOULD NOT appear\", function () {",
                "            throw new Error(\"GraphQL Internal Server Error detected: \" + JSON.stringify(internalErrors));",
                "        });",
                "    }",
                "}"
            ]
        }
    }
  ]
};

collections.forEach((file) => {
  const filePath = `${path}/${file}`;
  if (fs.existsSync(filePath)) {
    const collection = JSON.parse(fs.readFileSync(filePath, "utf8"));
    if (collection.item) {
      merged.item.push(...collection.item);
    }
  } else {
    console.warn(`Skipping missing file: ${filePath}`);
  }
});

fs.mkdirSync(`${path}/reports`, { recursive: true });
fs.writeFileSync(`${path}/reports/merged.json`, JSON.stringify(merged, null, 2));
fs.writeFileSync(`${path}/tmp_collection.json`, JSON.stringify(merged, null, 2));

console.log("Merged collection written to:");
console.log("  - /etc/newman/reports/merged.json");
console.log("  - /etc/newman/tmp_collection.json");