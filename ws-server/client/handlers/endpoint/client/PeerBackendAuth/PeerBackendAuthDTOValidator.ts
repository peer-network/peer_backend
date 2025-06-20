import { z } from "zod";
import { PeerBackendDTO } from "../PeerBackend/PeerBackendEndpointDTO";


// export const LoginDTOValidationSchema: z.Schema<PeerBackendDTO.LoginDTO> = z.object({
//     status: z.string().nonempty(),
//     ResponseCode: z.string().nonempty(),
//     accessToken : z.string(),
//     refreshToken : z.string()
// });

// export const HellloDTOValidationSchema: z.Schema<PeerBackendDTO.HelloDTO> = z.object({
//     currentVersion: z.string().nonempty(),
//     currentuserid: z.string(),
//     wikiLink: z.string().nonempty()
// });

// export const DailyGemsResultsUserDTOValidationSchema: z.Schema<PeerBackendDTO.GetDailyGemsResultsUserGems> = z.object({
//     userid : z.string().uuid(),
//     gems : z.number().positive()
// });
  
// export const DailyGemsResultsDataDTOValidationSchema: z.Schema<PeerBackendDTO.GetDailyGemsResultsData> = z.object({
//     totalGems : z.number().positive(),
//     data : z.array(DailyGemsResultsUserDTOValidationSchema)
// });