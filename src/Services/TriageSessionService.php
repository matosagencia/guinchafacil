<?php
declare(strict_types=1);

/**
 * Contrato de continuidade da cotaÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â§ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â£o entre prÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â©-cotaÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â§ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â£o, autenticaÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â§ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â£o e checkout.
 * O payload nÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â£o ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â© confiado ao navegador apÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â³s ser finalizado: fica apenas na sessÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½"ÃƒÂ¯Ã‚Â¿Ã‚Â½ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€šÃ‚Â ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?zÃƒâ€šÃ‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÂ¯Ã‚Â¿Ã‚Â½?ÃƒÂ¯Ã‚Â¿Ã‚Â½?TÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒâ€šÃ‚Â¬ÃƒÂ¯Ã‚Â¿Ã‚Â½.Ãƒâ€šÃ‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½'ÃƒÆ’Ã‚Â¢ÃƒÂ¯Ã‚Â¿Ã‚Â½,ÃƒÂ¯Ã‚Â¿Ã‚Â½Ãƒâ€¦Ã‚Â¡ÃƒÂ¯Ã‚Â¿Ã‚Â½fÃƒÂ¯Ã‚Â¿Ã‚Â½?sÃƒÂ¯Ã‚Â¿Ã‚Â½,Ãƒâ€šÃ‚Â£o.
 */
final class TriageSessionService
{
    public const SESSION_ID_KEY = 'triage_session_id';
    private const SESSIONS_KEY = 'triage_sessions';
    private const CHECKOUT_KEY = 'checkout_triagem';
    private const TTL_SECONDS = 1800;

    public static function finalizar(array $dadosCotacao): array
    {
        $dados = self::normalizar($dadosCotacao);
        $id = bin2hex(random_bytes(16));
        $agora = time();

        $_SESSION[self::SESSION_ID_KEY] = $id;
        $_SESSION[self::SESSIONS_KEY][$id] = [
            'id' => $id,
            'versao' => 1,
            'criado_em' => $agora,
            'expira_em' => $agora + self::TTL_SECONDS,
            'dados' => $dados,
        ];

        self::log('finalizar', 'TRIAGE-SAVE', 'Cotacao persistida na sessao.', ['triage_session_id' => $id]);
        return $_SESSION[self::SESSIONS_KEY][$id];
    }

    public static function reconstruir(string $id): ?array
    {
        $id = trim($id);
        $atual = (string)($_SESSION[self::SESSION_ID_KEY] ?? '');
        $registro = $_SESSION[self::SESSIONS_KEY][$id] ?? null;

        if ($id === '' || !is_array($registro) || !hash_equals($atual, $id)) {
            self::log('reconstruir', 'TRIAGE-REJECT', 'ID de triagem invalido ou ausente.', ['triage_session_id' => $id]);
            return null;
        }
        if ((int)($registro['expira_em'] ?? 0) <= time()) {
            unset($_SESSION[self::SESSIONS_KEY][$id], $_SESSION[self::SESSION_ID_KEY], $_SESSION[self::CHECKOUT_KEY]);
            self::log('reconstruir', 'TRIAGE-EXPIRED', 'Cotacao expirada removida da sessao.', ['triage_session_id' => $id]);
            return null;
        }

        $_SESSION[self::CHECKOUT_KEY] = $registro;
        self::log('reconstruir', 'TRIAGE-RESTORE', 'Checkout restaurado a partir da cotacao.', ['triage_session_id' => $id]);
        return $registro;
    }

    public static function registrarPedido(string $id, int $usuarioId, int $pedidoId): void
    {
        if (!isset($_SESSION[self::SESSIONS_KEY][$id]) || $pedidoId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Sessao de triagem invalida.');
        }
        $_SESSION[self::SESSIONS_KEY][$id]['usuario_id'] = $usuarioId;
        $_SESSION[self::SESSIONS_KEY][$id]['pedido_id'] = $pedidoId;
        $_SESSION[self::CHECKOUT_KEY] = $_SESSION[self::SESSIONS_KEY][$id];
        self::log('registrarPedido', 'TRIAGE-ORDER-LINK', 'Pedido vinculado a cotacao.', ['triage_session_id' => $id, 'pedido_id' => $pedidoId]);
    }

    private static function normalizar(array $entrada): array
    {
        $origem = is_array($entrada['origem'] ?? null) ? $entrada['origem'] : [];
        $destino = is_array($entrada['destino'] ?? null) ? $entrada['destino'] : [];
        $dados = [
            'tipo_problema' => trim((string)($entrada['tipo_problema'] ?? $entrada['problema'] ?? 'outro')),
            'descricao_problema' => trim((string)($entrada['descricao_problema'] ?? $entrada['descricao'] ?? '')),
            'lat_origem' => self::numero($entrada['lat_origem'] ?? $origem['lat'] ?? null),
            'lng_origem' => self::numero($entrada['lng_origem'] ?? $origem['lng'] ?? null),
            'endereco_origem' => trim((string)($entrada['endereco_origem'] ?? $origem['endereco'] ?? '')),
            'lat_destino' => self::numero($entrada['lat_destino'] ?? $destino['lat'] ?? null),
            'lng_destino' => self::numero($entrada['lng_destino'] ?? $destino['lng'] ?? null),
            'endereco_destino' => trim((string)($entrada['endereco_destino'] ?? $destino['endereco'] ?? '')),
            'distancia_km' => self::numero($entrada['distancia_km'] ?? $entrada['distancia'] ?? null),
            'custo_estimado' => self::numero($entrada['custo_estimado'] ?? $entrada['valor_estimado'] ?? null),
            'forma_pagamento' => in_array(
                strtolower(trim((string)($entrada['forma_pagamento'] ?? 'online'))),
                ['online', 'pago_na_chegada'],
                true
            ) ? strtolower(trim((string)($entrada['forma_pagamento'] ?? 'online'))) : 'online',
        ];

        require_once __DIR__ . '/../Models/Catalog/ServiceType.php';
        $serviceType = ServiceType::buscarPorSlugPublico($dados['tipo_problema']);
        if (!$serviceType) {
            throw new InvalidArgumentException('Sessao de triagem invalida.');
        }
        $attendanceMode = (string)($serviceType['attendance_mode'] ?? 'TOWING');
        $dados['service_type_id'] = (int)($serviceType['id'] ?? 0);
        $dados['attendance_mode'] = $attendanceMode;
        $dados['modalidade_socorro'] = $attendanceMode === 'ON_SITE' ? 'SOCORRO_LOCAL' : 'REBOQUE_PRANCHA';
        $dados['origem_funil'] = 'especialista';
        $requiresDestination = (bool)($serviceType['requires_destination'] ?? false)
            || (string)($serviceType['attendance_mode'] ?? '') === 'TOWING';
        $camposObrigatorios = ['lat_origem', 'lng_origem', 'distancia_km', 'custo_estimado'];
        if ($requiresDestination) {
            $camposObrigatorios = array_merge($camposObrigatorios, ['lat_destino', 'lng_destino']);
        }
        foreach ($camposObrigatorios as $campo) {
            throw new InvalidArgumentException('Sessao de triagem invalida.');
        }
        if ($dados['endereco_origem'] === '' || ($requiresDestination && $dados['endereco_destino'] === '') || $dados['distancia_km'] < 0 || $dados['custo_estimado'] < 0) {
            throw new InvalidArgumentException('Sessao de triagem invalida.');
        }
        return $dados;
    }

    private static function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float)$valor : null;
    }

    private static function log(string $funcao, string $fase, string $mensagem, array $contexto): void
    {
        if (class_exists('Logger')) {
            Logger::log(Logger::LEVEL_INFO, 'TriageSessionService', $funcao, 'triage', "[{$fase}] {$mensagem}", $contexto);
            return;
        }
        error_log('[TriageSessionService][' . $fase . '] ' . $mensagem);
    }
}
