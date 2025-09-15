<?php

namespace Fawaz\App;

use Fawaz\Database\UserMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\Mailer;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;
use Fawaz\Database\PeerTokenMapper;

class AlphaMintService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
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
            $this->logger->warning('Unauthorized access attempt');
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
            return self::respondWithError(60501);
        }

        $this->logger->info('UserService.alphaMint started');

        try {

            $alphaUserAc = $this->userMapper->loadByEmail('alpha_mint@peerapp.de');
            
            // If Alpha Mint account does not exist, create it
            if(!$alphaUserAc){
                $alphaMintAccount = [
                    'email' => 'alpha_mint@peerapp.de',
                    'password' => $_ENV['ALPA_MINT_PASSWORD'],
                    'username' => 'AlphaMint',
                ];

                $this->userService->createUser($alphaMintAccount);

                $alphaUserAc = $this->userMapper->loadByEmail('alpha_mint@peerapp.de');
            }


            if($alphaUserAc && $alphaUserAc->getUserId()){

                $mintUserId = $alphaUserAc->getUserId();
                
                // Get Alpha Users from Alpha_tokens_to_Peer_tokens.json file
                $alphaUsers = json_decode(file_get_contents(__DIR__ . '/../../runtime-data/Alpha_tokens_to_Peer_tokens.json'), true);

                if(!$alphaUsers || !is_array($alphaUsers)) {
                    $this->logger->error('Failed to load alpha users data from JSON file');
                    return self::respondWithError(41020);
                }
                $userCounts = 0;
                $excluedUsers = [];
                foreach ($alphaUsers as $key => $usr) {
                    $userData = $this->userMapper->checkIfNameAndSlugExist($usr['peer_username'], $usr['peer_app_slug']);
                    if($userData){
                        $userCounts++;
                    }else{
                        $excluedUsers[] = $usr['peer_username'];
                    }
                }

                if(count($alphaUsers) == $userCounts){
                    $totalAlphaUserMinted = 0;
                    foreach ($alphaUsers as $key => $usr) {
                        $userData = $this->userMapper->getUserByNameAndSlug($usr['peer_username'], $usr['peer_app_slug']);

                        if($userData){
                            $receipientUserId = $userData->getUserId();
                            $args = [
                                'recipient' => $receipientUserId,
                                'numberoftokens' => $usr['alpha_user_tokens'],
                                'message' => 'Alpha Token Migration',
                            ];
                            $this->peerTokenMapper->transferToken($mintUserId, $args);

                            $totalAlphaUserMinted++;
                        }
                    }
                }else{
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
            $this->logger->error('Failed to mint alpha', ['exception' => $e]);
            return self::respondWithError(41020);
        }
    }

}
