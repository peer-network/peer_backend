import { z } from "zod";
import { ClientTypes } from "../../domain/GemsResultsData";

const DailyGemsResultsUserValidationSchema: z.Schema<ClientTypes.GemsResultsUserGems> = z.object({
  userid : z.string().uuid(),
  gems : z.number().positive()
});

export const DailyGemsResultsDataValidationSchema: z.Schema<ClientTypes.GemsResultsData> = z.object({
  totalGems : z.number().positive(),
  data : z.array(DailyGemsResultsUserValidationSchema)
});

