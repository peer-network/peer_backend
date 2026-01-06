Session : codex resume 019b9265-dc57-7080-ae74-2c8e15c5efef

# User Input Length Validations

Chat link: (CLI session does not expose a shareable URL.)

## Key Findings
- `PeerInputFilter` and `PeerInputGenericValidator` provide reusable `StringLength` checks for passwords (8–128 chars), usernames (3–23 chars, `[a-zA-Z0-9_-]`, at least one letter), tags (2–53 chars), Solana pubkeys (43–44 chars), phone numbers (9–21 chars by regex), and activation/reset tokens (exactly 64 hex characters) per `src/Filter/PeerInputFilter.php:676-843` and `src/Filter/PeerInputGenericValidator.php:261-520` with limits sourced from `src/config/constants/ConstantsConfig.php:313-523`.
- Chat/message payload validators (`PeerInputFilter::ValidateChatStructure`, `ValidateParticipantStructure`, `ValidateUserStructure`, `ValidatePostStructure`, `ValidatePostPureStructure`) enforce chat message contents at 1–500 chars, participant/user avatars 0/30–100 chars, profile post titles 2–63 chars, media blobs 30–3000 chars, and media descriptions 3–500 chars (`src/Filter/PeerInputFilter.php:551-674`).
- Model-level filters mirror these bounds for CRUD endpoints: `src/App/Post.php:143-195`, `src/App/PostAdvanced.php:282-325`, and `src/App/PostMedia.php:143-200` constrain `title`, `media`, `cover`, `mediadescription`, and serialized `options` using the POST constants; `src/App/Comment*.php` variants restrict `content` to 2–200 chars as defined in `ConstantsConfig::comment()`.
- User/profile flows (`src/App/User.php:436-456`, `src/App/UserAdvanced.php:525-545`, `src/App/Profile.php:392-412`, `src/App/ProfilUser.php:141-150`) cap avatar URLs at 0–100 chars and biographies at 3–5000 chars, aligning with the shared `ValidateUserStructure` helper.
- Contact forms and wallet/token actions apply their own string limits: `src/App/Contactus.php:150-170` requires contact names 3–53 chars and messages 3–500 chars; `src/App/PeerTokenService.php:101-124` limits optional transfer messages to 500 chars and disallows control chars/URLs via the `USER.TRANSFER_MESSAGE` config.
- Financial/miscellaneous tokens also have fixed lengths: pool/wallet tokens must be exactly 12 characters (`src/App/Pool.php:162-172`, `src/App/Wallet.php:164-174`), multipart uploads require eligibility tokens 30–1000 chars (`src/App/Models/MultipartPost.php:600-606`), and transaction records constrain `transactiontype`, `transferaction`, and memo `message` fields between 0–200 chars (`src/App/Models/Transaction.php:100-135`).
