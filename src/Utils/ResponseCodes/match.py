import json
from pathlib import Path

# Create two sample files for demonstration
file1_path = Path("todo.json")
file2_path = Path("responseCodesForEditing.json")

# # Sample content for file1 and file2
# file1_content = {
#     "10401": {
#         "comment": "Success: Contact created successfully",
#         "userFriendlyComment": "Your message has been sent successfully!"
#     },
#     "10402": {
#         "comment": "Error: Email already exists",
#         "userFriendlyComment": ""
#     },
#     "10403": {
#         "comment": "Error: Invalid phone number",
#         "userFriendlyComment": "Please enter a valid phone number."
#     }
# }

# file2_content = {
#     "10401": {
#         "comment": "Success: Contact created successfully",
#         "userFriendlyComment": ""
#     },
#     "10402": {
#         "comment": "Error: Email already exists",
#         "userFriendlyComment": ""
#     },
#     "10403": {
#         "comment": "Error: Invalid phone number",
#         "userFriendlyComment": ""
#     }
# }

# # Write the sample content to files
# file1_path.write_text(json.dumps(file1_content, indent=4))
# file2_path.write_text(json.dumps(file2_content, indent=4))

# Load the JSON data from both files
with open(file1_path, 'r') as f1, open(file2_path, 'r') as f2:
    data1 = json.load(f1)
    data2 = json.load(f2)

# Update userFriendlyComment in data2 if it's non-empty in data1
for key, value in data1.items():
    # if value.get("userFriendlyComment"):
    #     if key in data2:
            data2[key]["userFriendlyComment"] = value["userFriendlyComment"]

# Save the updated data2 back to file2
with open("res.json", 'w') as f2:
    json.dump(data2, f2, indent=4)
