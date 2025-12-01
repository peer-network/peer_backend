# Backend Architecture - Step-by-Step Workflow
This document outlines the complete backend workflow for the peer_backend application, using the `searchPosts` GraphQL query as a practical example.
##  Complete Request Flow
### **Step 1: Client Request**
```http
POST /graphql
Content-Type: application/json
Authorization: Bearer <JWT_TOKEN>
{
  "query": "searchPosts(searchBy: TITLE, searchTerm: 'nature', limit: 10) { posts { postid title contenttype user { username } } totalCount }"
}
```
**What happens:**
- Client sends GraphQL query via HTTP POST
- Request includes authentication token
- Query specifies search parameters and desired response fields
---
### **Step 2: Entry Point**
```
public/index.php
    ↓
Slim App Router
    ↓
GraphQLHandler::handle()
```
**What happens:**
- `index.php` initializes the Slim Framework application
- Router matches `/graphql` endpoint
- Request is forwarded to `GraphQLHandler`
---
### **Step 3: Authentication Layer**
```
Extract Bearer Token
    ↓
JWT Validation
    ↓
Set User Context
```
**What happens:**
- Extract JWT token from `Authorization` header
- Validate token signature and expiration
- Set current user ID and roles in context
- If invalid token, return authentication error
---
### **Step 4: Schema Selection**
```
if (authenticated) → schema.graphl
else → schemaguest.graphl
```
**What happens:**
- Authenticated users get full schema (`schema.graphl`)
- Guest users get limited schema (`schemaguest.graphl`)
- `searchPosts` query will only available in authenticated schema
---
### **Step 5: GraphQL Processing**
```
Parse Query
    ↓
Validate Against Schema
    ↓
Route to Resolver
```
**What happens:**
- Parse GraphQL query syntax
- Validate query fields exist in schema
- Check if user has permission for requested fields
- Route `searchPosts` to appropriate resolver method
---
### **Step 6: Resolver Layer**
```
GraphQLSchemaBuilder::resolveSearchPosts()
    ↓
Validate Input Parameters
    ↓
Check Authentication
```
**File:** `src/GraphQLSchemaBuilder.php`
**What happens:**
- Validate `searchBy` parameter (TITLE, DESCRIPTION, BOTH)
- Validate `searchTerm` length (minimum 3 characters)
- Check user authentication status
- Validate pagination parameters (offset, limit)
---
### **Step 7: Service Layer**
```
PostService::searchPosts()
    ↓
Logic Validation
    ↓
Parameter Sanitization
```
**File:** `src/Services/PostService.php`
**What happens:**
- Apply rules and validation
- Sanitize search terms for security
- Format parameters for database layer
- Handle content filtering logic
---
### **Step 8: Data Access Layer**
```
PostMapper::searchPosts()
    ↓
Build SQL Query
    ↓
Execute with PDO
```
**File:** `src/Database/PostMapper.php`
**What happens:**
- Build PostgreSQL query with proper table aliases
- Use `ILIKE` for case-insensitive search
- Apply filters (content type, user status)
- Use prepared statements 
- Execute query with parameter binding
---
### **Step 9: Database Layer**
```
PostgreSQL Query Execution
    ↓
Return Raw Results
```
**Example Query:**
```sql
SELECT DISTINCT p.postid, p.title, p.mediadescription, u.username 
FROM posts p 
INNER JOIN users u ON p.userid = u.uid 
WHERE p.feedid IS NULL 
  AND p.title ILIKE '%nature%' 
  AND u.status = 0 
ORDER BY p.createdat DESC 
LIMIT 10 OFFSET 0
```
**What happens:**
- Execute optimized PostgreSQL query
- Return raw database results
- Handle database errors gracefully
---
### **Step 10: Response Chain**
```
PostMapper → PostAdvanced Objects
    ↓
PostService → Formatted Response
    ↓
GraphQLSchemaBuilder → GraphQL Response
    ↓
GraphQLHandler → HTTP Response
```
**What happens:**
- **PostMapper**: Convert raw data to `PostAdvanced` objects
- **PostService**: Apply logic formatting
- **GraphQLSchemaBuilder**: Format for GraphQL response structure
- **GraphQLHandler**: Convert to HTTP JSON response
---
##  Architecture Layers Summary

---
##  Security Flow

---
##   Performance Optimizations

---
##  Testing Points
---


