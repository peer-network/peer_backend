<?php

namespace Fawaz\App;

use Fawaz\Database\UserMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\Mailer;
use Fawaz\Services\TokenTransfer\Strategies\UserToUserTransferStrategy;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Database\PeerTokenMapper;

class AlphaMintService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected UserMapper $userMapper,
        protected UserService $userService,
        protected WalletMapper $walletMapper,
        protected PeerTokenMapper $peerTokenMapper,
        protected Mailer $mailer
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->error('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    /**
     * Run Alpha Minting Process
     *
     * This function is used to mint Alpha Tokens for the Alpha Mint account and
     * distribute them to the Alpha Users as per the Alpha_tokens_to_Peer_tokens.json file
     */
    public function alphaMint(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('AlphaMintService.alphaMint: Authentication failed');
            return self::respondWithError(60501);
        }

        $this->logger->info('AlphaMintService.alphaMint started');
        try {

            $alphaUserAc = $this->userMapper->loadByEmail('alpha_mint@peerapp.de');

            // If Alpha Mint account does not exist, create it
            if (!$alphaUserAc) {
                $alphaMintAccount = [
                    'email' => 'alpha_mint@peerapp.de',
                    'password' => $_ENV['ALPA_MINT_PASSWORD'],
                    'username' => 'AlphaMint',
                ];

                $this->userService->createUser($alphaMintAccount);

                $alphaUserAc = $this->userMapper->loadByEmail('alpha_mint@peerapp.de');
            }


            if ($alphaUserAc && $alphaUserAc->getUserId()) {

                $mintUserId = $alphaUserAc->getUserId();

                // Get Alpha Users from Alpha_tokens_to_Peer_tokens.json file
                $alphaUsers = json_decode(file_get_contents(__DIR__ . '/../../runtime-data/Alpha_tokens_to_Peer_tokens.json'), true);

                if (!$alphaUsers || !is_array($alphaUsers)) {
                    $this->logger->error('AlphaMintService.alphaMint: Failed to load alpha users data from JSON file');
                    return self::respondWithError(41020);
                }
                $userCounts = 0;
                $excluedUsers = [];
                foreach ($alphaUsers as $key => $usr) {
                    $userData = $this->userMapper->checkIfNameAndSlugExist($usr['peer_username'], $usr['peer_app_slug']);
                    if ($userData) {
                        $userCounts++;
                    } else {
                        $excluedUsers[] = $usr['peer_username'];
                    }
                }

                if (count($alphaUsers) == $userCounts) {
                    $totalAlphaUserMinted = 0;
                    foreach ($alphaUsers as $key => $usr) {
                        $userData = $this->userMapper->getUserByNameAndSlug($usr['peer_username'], $usr['peer_app_slug']);

                        if ($userData) {
                            $receipientUserId = $userData->getUserId();
                            // Skip duplicate transfer if same user and amount already credited
                            $amount = (string)$usr['alpha_user_tokens'];
                            if ($this->peerTokenMapper->hasExistingTransfer($mintUserId, $receipientUserId, $amount)) {
                                $this->logger->info('AlphaMintService.alphaMint: Duplicate alpha mint transfer skipped', [
                                    'recipient' => $receipientUserId,
                                    'amount' => $amount,
                                ]);
                                continue;
                            }
                            $this->peerTokenMapper->transferToken(
                                $amount,
                                new UserToUserTransferStrategy(),
                                $alphaUserAc,
                                $userData,
                                'Alpha Token Migration',
                            );

                            $totalAlphaUserMinted++;
                        }
                    }
                } else {
                    $this->userMapper->delete($mintUserId);
                    return [
                        'status' => 'error: Some users not found' . ' - ' . implode(', ', $excluedUsers),
                        'ResponseCode' => 404,
                    ];
                }
                $this->userMapper->delete($mintUserId);
                return [
                    'status' => 'success',
                    'ResponseCode' => 200,
                ];
            }

            return [
                'status' => 'error',
                'ResponseCode' => 500
            ];
        } catch (\Throwable $e) {
            $this->logger->error('AlphaMintService.alphaMint: Failed to mint alpha', ['exception' => $e]);
            return self::respondWithError(41020);
        }
    }

}
