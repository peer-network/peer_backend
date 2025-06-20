import { randomUUID } from 'crypto';
import { IPeerBackendClientEndpoint } from '../../../../interfaces/endpoints/client/peerBackend/IPeerBackendClientEndpoint'
import { ClientTypes } from '../../../../domain/GemsResultsData';

export default class MockPeerBackendClientEndpointImpl implements IPeerBackendClientEndpoint {
    
    async getHello() : Promise<ClientTypes.HelloData> {
        return new ClientTypes.HelloData(
            "this is a mock server",
            "this is a mock server",
            "this is a mock server",
        )
    }

    async getPeerDailyGemsResults(day : ClientTypes.DayFilterType) : Promise<ClientTypes.GemsResultsData> {
        return {
            data: [
                    {
                        userid: randomUUID(),
                        walletid:"GoGELpnDCUo3XtiHT8nc2L5k8JP1uTmpS14Q6tTsc3Jc",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"F2cYZUCWFECQuXtrB8m7qhnekg5LfP2Gj4tANv7uND1H",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"27AiRNWETjdJxKkNw7GfHQKFv1uGfsbAmqGKehjJ7m4E",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"8tRMVrjgyCQdao7SHQbJRELwWKEGnyoc8wQy46p2tZA6",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"39SPpcRb5tyjgsTTPtPdoL1VDfjxGBemwuvdXtjBUapi",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"BE6KqwpWE5M6WvAB9N3cJsgU24g1cHkoCFR7b54oCpdX",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"DKxAU3wucNrmN4yZ4kdSS9P7rPFub4XR7bEPHJThdqe9",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"CiLgfHxA9vwVbbvhBp7YMrpb8e5XTmej76AyzeCZxy6h",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"Chx6VCt22uKZhLJmndwBNQmNxYsCHnQzBKM8cim8DTQE",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"3HzwqASJpNb1sH618URMMJVLArrGmNxAUyYi28SriJw6",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"BoSneAsUYtLLW5bLuTRqWtRUvjQWMefeMni6rYH93vvn",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"BX1gA9vkSVgUgduohvQuiXRVo45fLZSWdPoHj6y4g5aa",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"6Fz3dARv6SYZpy7A5UAzfDugiKVCb1aqQcuzegY1KTpX",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"A22RVCBuutnkH4BjGScPbF63XkyPLBzRASdhCC1gpQAL",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"AAgmgPLacHP9Q86yXANrkqck7Cb48kJLYngwfe5xkgUd",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"3v8mbAhFo65LDj6TikbDzJkaVpWvK9utgC6mRmXAgeri",
                        gems: 0.25,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"wm49jgV8CcPTtFkUNcTy52evCaYVzwFFZdK9FiPckvu",
                        gems: 3,
                    },
                    {
                        userid: randomUUID(),
                        walletid:"F7zGAqsnb7PfxRc1ZGTbYMXdnVqH3cX3qKAgtdLZeHDw",
                        gems: 0.25,
                    }
                    ],
            totalGems: 26.5
        }
    }

//     async getPeerDailyGemsStatus() : Promise<ClientTypes> {
//       return new PeerBackendDTO.GetDailyGemsStatusResponse({
//         status : "test status"
//       })
//   }
}