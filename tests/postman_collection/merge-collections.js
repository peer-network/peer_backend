const fs = require("fs");
const path = "/etc/newman";
 
const collections = [
  "001_graphql_postman_collection.json",
  "002_graphql_postman_collection.json",
  "003_graphql_postman_collection.json",
  "004_graphql_postman_collection.json"
];
 
let merged = {
  info: {
    name: "Merged Collection",
    schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  item: [],
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