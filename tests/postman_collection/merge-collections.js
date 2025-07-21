const fs = require("fs");
const path = "/etc/newman";
 
const collections = [
  "001_graphql_postman_collection.json",
  "002_graphql_postman_collection.json",
  "003_graphql_postman_collection.json"
];
 
let merged = {
  info: {
    name: "Merged Collection",
    schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  item: [],
};
 
collections.forEach((file) => {
  const collection = JSON.parse(fs.readFileSync(`${path}/${file}`, "utf8"));
  if (collection.item) {
    merged.item.push(...collection.item);
  }
});
 
fs.writeFileSync(`${path}/reports/merged.json`, JSON.stringify(merged, null, 2));